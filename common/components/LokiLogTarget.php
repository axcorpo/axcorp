<?php

namespace common\components;

use Yii;

/**
 * Loki log target that derives the per-tenant `tenant` label at runtime from the
 * application base path: a tenant app lives under `workspaces/<domain>/`, so the
 * label becomes the domain (e.g. `econstructii.ro`). The source/dev workspace app
 * (base path under `workspace/`) gets no `tenant` label.
 *
 * This avoids relying on per-tenant config: the base target is defined once in the
 * shared workspace config and the tenant identity is resolved from the running app.
 *
 * Structured (JSON) messages are scrubbed of personal data before leaving the app:
 * Loki is an external store, so PII stays only in the local runtime logs. Events
 * keep their record ids, which is enough to look the person up in the DB.
 */
class LokiLogTarget extends \cebe\lokilogtarget\LokiLogTarget
{
	/**
	 * @var array Client-side HTTP errors (404s from bots/dead links, 403s, etc.) are
	 * internet background noise, not application defects: they stay in the local file
	 * logs but are not shipped to Loki, so `level="error"` in Grafana means something
	 * actually broke — and alerts can key on it directly.
	 */
	public $except = [
		'yii\debug\Module::checkAccess',
		'yii\web\HttpException:4*',
	];

	/**
	 * @var array Context sent to Loki is limited to the request essentials: the full
	 * $_SERVER/$_COOKIE/$_SESSION dumps stay in the local file logs only — they are
	 * noisy in Grafana and leak session/environment details to an external store.
	 */
	public $logVars = [
		'_GET',
		'_POST',
		'_SERVER.REQUEST_URI',
		'_SERVER.REQUEST_METHOD',
		'_SERVER.HTTP_REFERER',
		'_SERVER.HTTP_USER_AGENT',
		'_SERVER.REMOTE_ADDR',
	];

	/**
	 * @var string[] context keys (lowercase) whose values are redacted before pushing.
	 * Emails keep their domain; everything else is replaced entirely.
	 */
	public $sensitiveKeys = [
		'email', 'personal_id_number', 'cnp', 'first_name', 'last_name', 'middle_name',
		'nume', 'prenume', 'name', 'address', 'adresa', 'phone', 'password', 'auth_key',
	];

	/**
	 * @var string Transport for the push request. The package builds its client without
	 * naming one, so yii2-httpclient falls back to StreamTransport, which opens the URL
	 * with fopen() — and the production host runs with allow_url_fopen=0. Every flush
	 * then failed, and since the dispatcher reports a failing target as a warning, each
	 * error in the log came with a second entry saying the first could not be shipped.
	 */
	public $transport = 'yii\httpclient\CurlTransport';

	public function init()
	{
		parent::init();

		if (($tenant = static::tenantLabel()) !== null) {
			$this->labels['tenant'] = $tenant;
		}
	}

	/**
	 * @inheritdoc
	 *
	 * Same client the package builds, over cURL. Falls back to whatever the package
	 * chose when the extension is missing, so a host without curl keeps its previous
	 * behaviour instead of failing in a new way.
	 *
	 * @return \yii\httpclient\Client
	 */
	protected function getClient()
	{
		$client = parent::getClient();

		if (extension_loaded('curl')) {
			$client->transport = $this->transport;
		}

		return $client;
	}

	/**
	 * The `tenant` label of the running app — the domain of its workspaces/<domain>/
	 * base path — or null for the source workspace app. Shared with the dashboard,
	 * which links into Grafana pre-filtered on this label.
	 *
	 * @return string|null
	 */
	public static function tenantLabel()
	{
		if (preg_match('~[/\\\\]workspaces[/\\\\]([^/\\\\]+)[/\\\\]~', Yii::$app->getBasePath() . DIRECTORY_SEPARATOR, $m)) {
			return $m[1];
		}
		return null;
	}

	public function formatMessage($message)
	{
		if (is_string($message[0])) {
			$decoded = json_decode($message[0], true);
			if (is_array($decoded)) {
				$message[0] = json_encode(
					$this->scrub($decoded),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
			}
		}

		return parent::formatMessage($message);
	}

	private function scrub(array $data)
	{
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$data[$key] = $this->scrub($value);
			} elseif (in_array(strtolower((string) $key), $this->sensitiveKeys, true) && $value !== null && $value !== '') {
				$data[$key] = strtolower((string) $key) === 'email'
					? preg_replace('/^[^@]*/', '***', (string) $value)
					: '[redacted]';
			}
		}

		return $data;
	}
}
