<?php

namespace backend\modules\setting\models;

use common\models\Setting;
use common\traits\SecretSettingTrait;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class CommercialSettingForm extends Setting
{
	use SecretSettingTrait;

	/**
	 * {@inheritdoc}
	 */
	public function secretAttributes()
	{
		return [
			'openApiKey',
		];
	}

	const GATEWAY_OPENAPI = 'OpenApi';

	/**
	 * @var string The default transport used to send SMS.
	 */
	public $defaultGateway;

	/**
	 * @var string The API base URL.
	 */
	public $openApiBaseUrl;

	/**
	 * @var string The API openApiKey.
	 */
	public $openApiKey;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->defaultGateway = self::GATEWAY_OPENAPI;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['openApiBaseUrl', 'openApiKey'], 'required', 'when' => function () {
				return $this->defaultGateway == self::GATEWAY_OPENAPI;
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[defaultGateway]\"]").val() == "' . self::GATEWAY_OPENAPI . '";
			}'],
			[['openApiBaseUrl', 'openApiKey'], 'trim'],
			[['openApiBaseUrl'], 'url'],
			[['openApiBaseUrl', 'openApiKey'], 'string'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'defaultGateway' => Yii::t('label', 'Default Gateway'),
			'openApiBaseUrl' => Yii::t('label', 'Base URL'),
			'openApiKey' => Yii::t('label', 'API Key'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->setAttributes($this->getUnserializedValue('setting'));

		// Captured before the request can overwrite them; see the trait.
		$this->rememberSecrets();
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord|self
	 */
	public function saveModel()
	{
		$this->name = 'commercial';
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			'defaultGateway' => $this->defaultGateway,
			'openApiBaseUrl' => $this->openApiBaseUrl,
			'openApiKey' => $this->openApiKey,
		]);

		return $this->save() ? $this : null;
	}
}
