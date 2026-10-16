<?php

namespace common\components;

use GuzzleHttp\Client;
use Yii;
use yii\base\InvalidConfigException;

/**
 * cPanel API client for the workspace installer.
 *
 * Replaces tws\cpanel\CPanel, whose authentication never reached the server: the
 * credentials were passed as
 *
 *     addHeaders(['Authorization: Basic ' => base64_encode("{$user}:{$pass}")])
 *
 * — the header NAME being "Authorization: Basic ", not its value. Yii normalises that
 * name and the request goes out carrying
 *
 *     Authorization:-Basic-: dXNlcjpwYXNz
 *
 * with no Authorization header at all, so every call came back 401 and the installer,
 * which only warned on cPanel failures, silently created no database, granted no
 * privileges and added no addon domain.
 *
 * On top of the fix this supports API tokens (cPanel » Security » Manage API Tokens),
 * which is the only option for an account with two-factor authentication, and keeps
 * the account password off the wire:
 *
 *     'cPanel' => [
 *         'class' => 'common\components\CPanel',
 *         'baseUrl' => 'https://example.com:2083',
 *         'username' => 'cpaneluser',
 *         'apiToken' => '...',
 *     ],
 *
 * Usage is unchanged:
 *
 *     Yii::$app->cPanel->uapi->Mysql->create_database(['name' => 'db'])
 *     Yii::$app->cPanel->api2->AddonDomain->addaddondomain([...])
 *
 * @property object $uapi cPanel UAPI (/execute/<Module>/<function>)
 * @property object $api2 cPanel API 2 (/json-api/cpanel)
 */
class CPanel extends \tws\cpanel\CPanel
{
	/**
	 * @var string|null cPanel API token. Takes precedence over the password.
	 */
	public $apiToken;

	/**
	 * @var int Request timeout in seconds. The installer makes these calls inside a web
	 * request, so a cPanel that stops answering must not hold the page open.
	 */
	public $timeout = 30;

	/**
	 * @var bool Whether to verify the cPanel certificate. Shared hosts routinely serve
	 * port 2083 with a certificate for the server's own hostname, not the account's.
	 */
	public $verifySsl = false;

	/**
	 * @inheritdoc
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		// Deliberately not parent::init(): it insists on a password, which an account
		// authenticating by token does not need.
		\yii\base\Component::init();

		if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
			throw new InvalidConfigException('The "baseUrl" property must be a valid URL.');
		}
		if (empty($this->username)) {
			throw new InvalidConfigException('The "username" property must be set to a non-empty value.');
		}
		if (empty($this->password) && empty($this->apiToken)) {
			throw new InvalidConfigException('Either "password" or "apiToken" must be set to a non-empty value.');
		}
	}

	/**
	 * True when the component holds real credentials rather than the CPANEL_* placeholders
	 * a dev environment ships with.
	 *
	 * @return bool
	 */
	public function isConfigured(): bool
	{
		foreach ([$this->baseUrl, $this->username, $this->password, $this->apiToken] as $value) {
			if (is_string($value) && stripos($value, 'CPANEL_') !== false) {
				return false;
			}
		}

		return !empty($this->baseUrl) && !empty($this->username) && (!empty($this->password) || !empty($this->apiToken));
	}

	/**
	 * The Authorization header this client sends. Token authentication uses cPanel's own
	 * scheme; the password path is ordinary Basic auth.
	 *
	 * @return string
	 */
	public function getAuthorizationHeader(): string
	{
		if (!empty($this->apiToken)) {
			return "cpanel {$this->username}:{$this->apiToken}";
		}

		return 'Basic ' . base64_encode("{$this->username}:{$this->password}");
	}

	/**
	 * @inheritdoc
	 *
	 * Returns the decoded body even for a refusal — cPanel puts the reason in there, and
	 * the callers read it. False means the response was not JSON at all (an HTML login
	 * page for a rejected credential, a proxy error page), which no caller can act on.
	 *
	 * @param string $function
	 * @param array $data
	 * @return array|false
	 */
	protected function makeRequest($function, $data = [])
	{
		if ($this->api === 'uapi') {
			$url = rtrim($this->baseUrl, '/') . "/execute/{$this->module}/{$function}";
			$query = $data;
		} else {
			$url = rtrim($this->baseUrl, '/') . '/json-api/cpanel';
			$query = array_merge([
				'cpanel_jsonapi_user' => $this->username,
				'cpanel_jsonapi_apiversion' => 2,
				'cpanel_jsonapi_module' => $this->module,
				'cpanel_jsonapi_func' => $function,
			], $data);
		}

		$options = [
			'headers' => [
				'Authorization' => $this->getAuthorizationHeader(),
				'Accept' => 'application/json',
			],
			'http_errors' => false,
		];
		// GET keeps the parameters in the query string, as cPanel expects; POST is used
		// for anything carrying a secret, so it stays out of the server's access log.
		if ($this->method === 'POST') {
			$options['form_params'] = $query;
		} else {
			$options['query'] = $query;
		}

		try {
			$response = (new Client(['verify' => $this->verifySsl, 'timeout' => $this->timeout]))
				->request($this->method === 'POST' ? 'POST' : 'GET', $url, $options);
		} catch (\Throwable $e) {
			Yii::error([
				'message' => 'cPanel request failed to complete.',
				'api' => $this->api,
				'module' => $this->module,
				'function' => $function,
				'error' => $e->getMessage(),
			], 'cpanel');

			return false;
		}

		$status = $response->getStatusCode();
		$decoded = json_decode((string) $response->getBody(), true);

		if (!is_array($decoded)) {
			Yii::error([
				'message' => "cPanel answered HTTP {$status} with a non-JSON body.",
				'api' => $this->api,
				'module' => $this->module,
				'function' => $function,
				'hint' => $status === 401
					? 'The credentials are rejected. An account with two-factor authentication cannot use a password here — configure apiToken.'
					: 'Check the base URL and whether API access is permitted for this account.',
			], 'cpanel');

			return false;
		}

		return $decoded;
	}
}
