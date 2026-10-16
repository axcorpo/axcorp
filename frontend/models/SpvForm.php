<?php

namespace frontend\models;

use common\models\Integration;
use common\models\WorkspaceHasIntegration;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\web\Cookie;

class SpvForm extends Model
{
	/**
	 * @var int Seconds a shareable authorize link stays valid (matches the `spv` cookie).
	 */
	const AUTHORIZE_TTL = 86400;

	/**
	 * Session key holding the OAuth `state` sent to ANAF, checked on the callback.
	 */
	const STATE_SESSION_KEY = 'spvOAuthState';

	/**
	 * @var string spv field.
	 */
	public $spv;

	/**
	 * @var int workspace id field.
	 */
	public $workspace_id;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['spv'], 'required'],
			[['workspace_id', 'workEmail'], 'safe'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [];
	}

	/**
	 * Sets accept cookies.
	 *
	 * @return bool whether the email was sent.
	 */
	public function setCookies()
	{
		$cookie = new Cookie([
			'name' => 'spv',
			'value' => $this->spv,
			'expire' => time() + 86400 * 1,
		]);
		Yii::$app->getResponse()->getCookies()->add($cookie);
		return true;
	}

	public function saveModel()
	{
		try {
			$errors = [];
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, 'https://logincert.anaf.ro/anaf-oauth2/v1/token');
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
				'grant_type' => 'authorization_code',
				'code' => $this->spv,
				'client_id' => Yii::$app->params['spv.clientId'],
				'client_secret' => Yii::$app->params['spv.clientSecret'],
				'redirect_uri' => Yii::$app->params['spv.redirect'],
				'token_content_type' => 'jwt',
			]));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);
			if ($response === false) {
				$errors[] = curl_error($curl);
			}
			curl_close($curl);
			$result = json_decode($response, true);

			if (!empty($errors)) {
				throw new \Exception();
			}
			$integration = Integration::find()
				->alias('i')
				->select([
					'i.*',
				])
				->joinWith([
					'workspaces w',
				])
				->where([
					'i.type' => Integration::TYPE_SPV,
				])
				->andFilterWhere([
					'w.id' => $this->workspace_id,
				])
				->one();
			if (empty($integration)) {
				$model = new Integration();
			} else {
				$model = Integration::findOne(['id' => $integration->id]);
			}
			if (!empty($result['access_token'])) {
				$model->name = 'Spaţiul Privat Virtual';
				$model->data = $response;
				$model->type = Integration::TYPE_SPV;
				$model->expire_at = date('Y-m-d H:i:s', (time() + $result['expires_in']));
				$model->status = Integration::STATUS_ACTIVE;
				$model->deleted = Integration::NO;
				if (!$model->save()) {
					throw new \Exception();
				} else {
					if (!empty($this->workspace_id)) {
						WorkspaceHasIntegration::deleteAll([
							'workspace_id' => $this->workspace_id,
							'integration_id' => $model->id,
						]);
						$workspaceIntegration = new WorkspaceHasIntegration();
						$workspaceIntegration->workspace_id = $this->workspace_id;
						$workspaceIntegration->integration_id = $model->id;
						if (!$workspaceIntegration->save()) {
							throw new \Exception();
						}
					}
				}
				$cookieCollection = Yii::$app->getResponse()->cookies;
				$cookieCollection->remove('spv');
			}
			return true;
		} catch(\Exception $e) {
			if (!empty($errors)) {
				foreach ($errors as $error) {
					if (!empty($error)) {
						$this->addError('', $error);
					}
				}
			}
			return false;
		}
	}

	/**
	 * Generates a short URL-friendly token from combined tokens.
	 *
	 * @param array $tokens
	 * @return string
	 */
	public function generateShortUrlToken(array $tokens)
	{
		// Combine tokens with a delimiter
		$combinedToken = implode('|', $tokens);

		// Compress the combined token
		$compressedToken = gzcompress($combinedToken, 9);

		// Encode the compressed token in a URL-friendly base64 format
		$shortToken = $this->base64UrlEncode($compressedToken);

		return $shortToken;
	}

	/**
	 * Decodes a short URL-friendly token back into an array of tokens.
	 *
	 * @param string $shortToken
	 * @return array
	 */
	public function decodeShortUrlToken($shortToken)
	{
		// Decode the base64 URL-encoded string
		$decodedToken = $this->base64UrlDecode($shortToken);

		// Decompress the decoded string
		$decompressedToken = gzuncompress($decodedToken);

		// Split the decompressed string back into tokens
		$tokens = explode('|', $decompressedToken);

		return $tokens;
	}

	/**
	 * Encodes data in a URL-friendly base64 format.
	 *
	 * @param string $data
	 * @return string
	 */
	private function base64UrlEncode($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Decodes a URL-friendly base64 encoded string.
	 *
	 * @param string $data
	 * @return string
	 */
	private function base64UrlDecode($data)
	{
		return base64_decode(strtr($data, '-_', '+/'));
	}

	/**
	 * Verifies the short URL-friendly token.
	 *
	 * @param string $shortToken
	 * @param array $tokens
	 * @return bool
	 */
	public function verifyShortUrlToken($shortToken, array $tokens)
	{
		// Re-generate the short token from the original tokens
		$expectedToken = $this->generateShortUrlToken($tokens);

		// Compare the provided short token with the expected one
		return hash_equals($shortToken, $expectedToken);
	}

	/**
	 * Builds the shareable "authorize" link token. The old short token was the client
	 * id and secret, gzipped — static, reversible and leaking the secret into the URL.
	 * This one is signed and expires, so a link only works for the day it was issued.
	 *
	 * @return string
	 */
	public static function generateAuthorizeToken()
	{
		return Yii::$app->security->hashData(
			'spv.' . (time() + static::AUTHORIZE_TTL),
			Yii::$app->request->cookieValidationKey
		);
	}

	/**
	 * @param string $token
	 * @return bool whether the token was issued by this app and has not expired
	 */
	public static function verifyAuthorizeToken($token)
	{
		if (!is_string($token) || $token === '') {
			return false;
		}
		$payload = Yii::$app->security->validateData($token, Yii::$app->request->cookieValidationKey);
		if ($payload === false || strpos($payload, 'spv.') !== 0) {
			return false;
		}
		$expiry = substr($payload, 4);
		return ctype_digit($expiry) && (int) $expiry >= time();
	}
}
