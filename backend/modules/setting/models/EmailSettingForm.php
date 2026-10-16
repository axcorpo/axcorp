<?php

namespace backend\modules\setting\models;

use common\models\Setting;
use common\traits\SecretSettingTrait;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class EmailSettingForm extends Setting
{
	use SecretSettingTrait;

	/**
	 * {@inheritdoc}
	 */
	public function secretAttributes()
	{
		return [
			'password',
		];
	}

	/**
	 * @var string The email address from which the emails will be sent.
	 */
	public $from;

	/**
	 * @var string The email address to which the emails will be sent.
	 */
	public $replyTo;

	/**
	 * @var string The SMTP server host.
	 */
	public $host = 'localhost';

	/**
	 * @var int The SMTP server port.
	 */
	public $port = 25;

	/**
	 * @var string The SMTP server encryption.
	 */
	public $encryption = 'tls';

	/**
	 * @var string The SMTP server username.
	 */
	public $username;

	/**
	 * @var string The SMTP server password.
	 */
	public $password;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['from', 'username', 'password'], 'required'],
			[['from', 'replyTo', 'host', 'encryption', 'username', 'password'], 'string'],
			[['from', 'replyTo', 'host', 'port', 'encryption', 'username', 'password'], 'trim'],
			[['port'], 'integer'],
			[['from', 'replyTo'], 'email'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'from' => Yii::t('label', 'From'),
			'replyTo' => Yii::t('label', 'Reply To'),
			'host' => Yii::t('label', 'Host'),
			'port' => Yii::t('label', 'Port'),
			'encryption' => Yii::t('label', 'Encryption'),
			'username' => Yii::t('label', 'Username'),
			'password' => Yii::t('label', 'Password'),
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
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$this->name = 'email';
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			'from' => $this->from,
			'replyTo' => $this->replyTo,
			'host' => $this->host,
			'port' => $this->port,
			'encryption' => $this->encryption,
			'username' => $this->username,
			'password' => $this->password,
		]);

		return $this->save() ? $this : null;
	}
}
