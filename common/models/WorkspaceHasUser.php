<?php

namespace common\models;

use tws\helpers\StringHelper;
use Yii;

/**
 * This is the model class for table "{{%workspace_has_user}}".
 *
 * @property int $workspace_id
 * @property int $user_id
 * @property int $default
 * @property string $auth_key
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $signup_token
 * @property string $login_token
 * @property string $username
 * @property string $email
 * @property string $phone
 * @property string $first_name
 * @property string $middle_name
 * @property string $last_name
 * @property string $image
 * @property int $gender
 * @property string $signature
 * @property string $last_activity
 * @property string $last_login
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Workspace $workspace
 * @property User $user
 */
class WorkspaceHasUser extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%workspace_has_user}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['workspace_id', 'user_id', 'first_name', 'last_name', 'status'], 'required'],
			[['signature'], 'string'],
			[['workspace_id', 'user_id', 'default', 'gender', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at', 'last_activity', 'last_login'], 'safe'],
			[['created_at', 'updated_at', 'last_activity', 'last_login'], 'default'],
			[['auth_key'], 'string', 'max' => 32],
			[['password_hash', 'password_reset_token', 'signup_token', 'login_token', 'username', 'email', 'phone', 'first_name', 'middle_name', 'last_name', 'image'], 'string', 'max' => 255],
			[['workspace_id', 'user_id'], 'unique', 'targetAttribute' => ['workspace_id', 'user_id']],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
			[['workspace_id'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['workspace_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'workspace_id' => Yii::t('label', 'Workspace ID'),
			'user_id' => Yii::t('label', 'User ID'),
			'default' => Yii::t('label', 'Default'),
			'auth_key' => Yii::t('label', 'Auth Key'),
			'password_hash' => Yii::t('label', 'Password Hash'),
			'password_reset_token' => Yii::t('label', 'Password Reset Token'),
			'signup_token' => Yii::t('label', 'Signup Token'),
			'login_token' => Yii::t('label', 'Login Token'),
			'username' => Yii::t('label', 'Username'),
			'email' => Yii::t('label', 'Email'),
			'phone' => Yii::t('label', 'Phone'),
			'first_name' => Yii::t('label', 'First Name'),
			'middle_name' => Yii::t('label', 'Middle Name'),
			'last_name' => Yii::t('label', 'Last Name'),
			'image' => Yii::t('label', 'Image'),
			'gender' => Yii::t('label', 'Gender'),
			'signature' => Yii::t('label', 'Signature'),
			'last_activity' => Yii::t('label', 'Last Activity'),
			'last_login' => Yii::t('label', 'Last Login'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspace()
	{
		return $this->hasOne(Workspace::class, ['id' => 'workspace_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	//region IDENTITY
	/**
	 * Generates password hash from password and sets it to the model.
	 *
	 * @param string $password
	 * @throws \yii\base\Exception
	 */
	public function setPassword($password)
	{
		$this->password_hash = Yii::$app->security->generatePasswordHash($password);
	}
	//endregion
}
