<?php

namespace common\models;

use common\helpers\DebugHelper;
use tws\behaviors\DateTimeBehavior;
use common\behaviors\SyncRelatedModelBehavior;
use DateTime;
use tws\helpers\StringHelper;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use tws\helpers\Url;
use yii\db\ActiveQuery;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%user}}".
 *
 * @property int $id
 * @property int $parent_id
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
 * @property Company[] $companies
 * @property EventLog[] $eventLogs
 * @property Setting[] $settings
 * @property Subscriber $subscriber
 * @property Affiliate|null $affiliate
 * @property SubscriptionHasUser[] $subscriptionHasUsers
 * @property Subscription[] $subscriptions
 * @property SupportTicket[] $supportTickets
 * @property User $parent
 * @property User[] $users
 * @property UserHasNotification[] $userHasNotifications
 * @property Notification[] $notifications
 * @property AuthAssignment $authAssignment
 * @property MarketingRecipient $marketingRecipient
 * @property WorkspaceHasUser[] $workspaceHasUsers
 * @property Workspace[] $workspaces
 * @property User $creator
 * @property User $updater
 *
 * @property string fullName
 * @property string fullNameInitials
 * @property string shortName
 * @property string|null imageUrl
 */
class User extends UuidActiveRecord implements \yii\web\IdentityInterface
{
	const EVENT_AFTER_SIGNUP = 'user.afterSignup';
	const EVENT_AFTER_ACTIVATE_ACCOUNT = 'user.afterActivateAccount';

	const ACCOUNT_ACTIVATION_AUTOMATIC = 1;
	const ACCOUNT_ACTIVATION_CONFIRMATION = 2;

	/**
	 * Token lifetimes (seconds) used when the matching setting is unset:
	 * userPasswordResetTokenExpiration / userSignupTokenExpiration.
	 */
	const PASSWORD_RESET_TOKEN_EXPIRATION = 3600;
	const SIGNUP_TOKEN_EXPIRATION = 604800;

	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%user}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
			],
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['last_activity', 'last_login'],
			],
			'SyncRelatedModelBehavior' => [
				'class' => SyncRelatedModelBehavior::class,
				'models' => [
					MarketingRecipient::class => [
						'filterBy' => 'email',
						'afterSave' => [
							'attributes' => ['email', 'phone', 'status'],
						],
						'afterDelete' => [
							'attributes' => ['status' => MarketingRecipient::STATUS_INACTIVE],
						],
					],
				],
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['parent_id', 'gender', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['first_name', 'last_name', 'status'], 'required'],
			[['signature'], 'string'],
			[['created_at', 'updated_at', 'last_activity', 'last_login'], 'safe'],
			[['created_at', 'updated_at', 'last_activity', 'last_login'], 'default'],
			[['auth_key'], 'string', 'max' => 32],
			[['password_hash', 'password_reset_token', 'signup_token', 'login_token', 'username', 'email', 'phone', 'first_name', 'middle_name', 'last_name', 'image'], 'string', 'max' => 255],
			[['username', 'email', 'phone', 'password_reset_token', 'signup_token', 'login_token'], 'unique'],
			[['email'], 'email'],
			['gender', 'in', 'range' => [static::GENDER_MALE, static::GENDER_FEMALE]],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'auth_key' => Yii::t('label', 'Auth Key'),
			'password_hash' => Yii::t('label', 'Password Hash'),
			'password_reset_token' => Yii::t('label', 'Password Reset Token'),
			'signup_token' => Yii::t('label', 'Signup Token'),
			'login_token' => Yii::t('label', 'Login Token'),
			'email' => Yii::t('label', 'Email'),
			'username' => Yii::t('label', 'Username'),
			'phone' => Yii::t('label', 'Phone'),
			'image' => Yii::t('label', 'Image'),
			'first_name' => Yii::t('label', 'First Name'),
			'middle_name' => Yii::t('label', 'Middle Name'),
			'last_name' => Yii::t('label', 'Last Name'),
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

	public function afterLogin($event)
	{
		if (Yii::$app->settings->get('enableTwoFactorAuthentication') && $username = Yii::$app->request->post()['LoginForm']['username']) {
			try {
				$model = new TwoFactorAuthenticationRequestForm();
				$model->username = $username;
				$model->sendRequest();
			} catch (\Exception $e) {
			}
		}
	}

	//region IDENTITY
	/**
	 * @inheritdoc
	 */
	public static function findIdentity($id)
	{
		$model = static::findOne([
			'id' => $id,
//			'status' => static::STATUS_ACTIVE,
			'deleted' => static::NO,
		]);

		if (!$model) {
			return null;
		}

		// Don't check the status for subscribers and for workspace users
		if ($model->getIsSubscriber() || $model->getWorkspaces()->exists()) {
			return $model;
		}

		return $model->status == static::STATUS_ACTIVE ? $model : null;
	}

	/**
	 * @inheritdoc
	 */
	public static function findIdentityByAccessToken($token, $type = null)
	{
		return static::findOne([
			'auth_key' => $token,
			'deleted' => self::NO,
			'status' => self::STATUS_ACTIVE,
		]);
	}

	/**
	 * Finds user by username (it can be any of username, email or phone).
	 *
	 * @param string $username
	 * @return array|\yii\db\ActiveRecord[]|\yii\db\ActiveRecord|static[]|static|null
	 */
	public static function findByUsername($username)
	{
		/** @var static $model */
		$model = static::find()
			->andWhere([
				'deleted' => static::NO,
			])
			->andWhere(['=', 'email', $username])
			->one();

		if ($model) {
			// Don't check the status for subscribers and for workspace users
			if (!$model->getSubscriber()->active()->deleted(false)->count() && !$model->getWorkspaces()->count()) {
				if ($model->status != static::STATUS_ACTIVE) {
					$model = null;
				}
			}
		}

		/** @var static[] $models */
		$models = static::find()
			->alias('u')
			->select([
				'u.id',
				'u.parent_id',
				'whu.*',
			])
			->joinWith(['workspaceHasUsers whu'], false, 'RIGHT JOIN')
			->andWhere([
				'whu.status' => WorkspaceHasUser::STATUS_ACTIVE,
				'whu.deleted' => WorkspaceHasUser::NO,
			])
			->andWhere(['=', 'whu.email', $username])
			->all();

		if ($model) {
			array_unshift($models, $model);
		}

		return $models;
	}

	/**
	 * Finds user by password reset token.
	 *
	 * @param string $token password reset token
	 * @return array|\yii\db\ActiveRecord[]|\yii\db\ActiveRecord|static[]|static|null
	 */
	public static function findByPasswordResetToken($token)
	{
		// Make sure that the token length is correct, since we are using LIKE to find the model
		if (strlen($token) !== 8) {
			return null;
		}

		$model = static::find()
            ->andWhere(new \yii\db\Expression('SUBSTRING([[password_reset_token]], 1, 8) = :token'), [':token' => $token])
			->andWhere([
				'deleted' => self::NO,
			])
			->one();

		// The code is short and typed by hand, so it must also age out.
		if ($model) {
			return static::isPasswordResetTokenValid($model->password_reset_token) ? $model : null;
		}

		$models = static::find()
			->alias('u')
			->select([
				'u.id',
				'u.parent_id',
				'whu.*',
			])
			->joinWith(['workspaceHasUsers whu'], false, 'RIGHT JOIN')
			->andWhere([
				'whu.deleted' => WorkspaceHasUser::NO,
			])
			->andWhere(['LIKE', 'whu.password_reset_token', "{$token}%", false])
			->all();
		$models = array_values(array_filter($models, function ($model) {
			return static::isPasswordResetTokenValid($model->password_reset_token);
		}));

		return $models ?: null;
	}

	/**
	 * Finds user by signup token.
	 *
	 * @param string $token signup token
	 * @return array|\yii\db\ActiveRecord|static|null
	 */
	public static function findBySignupToken($token)
	{
		// Make sure that the token length is correct, since we are using LIKE to find the model
		if (strlen($token) !== 8) {
			return null;
		}

		$model = static::find()
            ->andWhere(new \yii\db\Expression('SUBSTRING([[signup_token]], 1, 8) = :token'), [':token' => $token])
			->andWhere([
				'status' => static::STATUS_INACTIVE,
				'deleted' => static::NO,
			])
			->one();

		if ($model) {
			return static::isSignupTokenValid($model->signup_token) ? $model : null;
		}

		$model = static::find()
			->alias('u')
			->select([
				'u.id',
				'u.parent_id',
				'whu.*',
			])
			->joinWith(['workspaceHasUsers whu'], false, 'RIGHT JOIN')
			->andWhere([
				'whu.status' => WorkspaceHasUser::STATUS_INACTIVE,
				'whu.deleted' => WorkspaceHasUser::NO,
			])
			->andWhere(['LIKE', 'whu.signup_token', "{$token}%", false])
			->one();

		return $model && static::isSignupTokenValid($model->signup_token) ? $model : null;
	}

	/**
	 * Generates a unique password reset token.
	 *
	 * @param int $length
	 * @return string
	 */
	public static function generatePasswordResetToken($length = 8)
	{
		$token = StringHelper::generateRandomAlphanumerics($length) . '_' . time();

		if (User::find()->where(['password_reset_token' => $token])->exists()) {
			return static::generatePasswordResetToken($length);
		}

		return $token;
	}

	/**
	 * Finds out if password reset token is valid.
	 *
	 * @param string $token password reset token
	 * @return bool
	 */
	public static function isPasswordResetTokenValid($token)
	{
		if (empty($token)) {
			return false;
		}

		$timestamp = (int) substr($token, strrpos($token, '_') + 1);
		// The setting is optional; an unset one must not mean "expired at once".
		$expire = (int) Yii::$app->settings->get('userPasswordResetTokenExpiration') ?: static::PASSWORD_RESET_TOKEN_EXPIRATION;

		return ($timestamp + $expire) >= time();
	}

	/**
	 * Generates a unique signup token.
	 *
	 * @param int $length
	 * @return string
	 */
	public static function generateSignupToken($length = 8)
	{
		$token = StringHelper::generateRandomAlphanumerics($length) . '_' . time();

		if (User::find()->where(['signup_token' => $token])->exists()) {
			return static::generateSignupToken($length);
		}

		return $token;
	}

	/**
	 * Finds out if signup token is valid.
	 *
	 * @param string $token signup token
	 * @return bool
	 */
	public static function isSignupTokenValid($token)
	{
		if (empty($token)) {
			return false;
		}

		$timestamp = (int) substr($token, strrpos($token, '_') + 1);
		// The setting is optional; an unset one must not mean "expired at once".
		$expire = (int) Yii::$app->settings->get('userSignupTokenExpiration') ?: static::SIGNUP_TOKEN_EXPIRATION;

		return ($timestamp + $expire) >= time();
	}

	/**
	 * Generates a unique login token.
	 *
	 * @param int $length
	 * @return string
	 */
	public static function generateLoginToken($length = 8)
	{
		$token = StringHelper::generateRandomAlphanumerics($length) . '_' . time();

		if (User::find()->where(['login_token' => $token])->exists()) {
			return static::generateLoginToken($length);
		}

		return $token;
	}

	/**
	 * Finds user by login token.
	 *
	 * @param string $token login token
	 * @return array|\yii\db\ActiveRecord[]|\yii\db\ActiveRecord|static[]|static|null
	 */
	public static function findByLoginToken($token)
	{
		// Make sure that the token length is correct, since we are using LIKE to find the model
		if (strlen($token) !== 8) {
			return null;
		}

		$model = static::find()
			->andWhere(new \yii\db\Expression('SUBSTRING([[login_token]], 1, 8) = :token'), [':token' => $token])
			->andWhere([
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->one();

		if ($model) {
			return $model;
		}

		return static::find()
			->alias('u')
			->select([
				'u.id',
				'u.parent_id',
				'whu.*',
			])
			->joinWith(['workspaceHasUsers whu'], false, 'RIGHT JOIN')
			->andWhere([
				'whu.status' => WorkspaceHasUser::STATUS_ACTIVE,
				'whu.deleted' => WorkspaceHasUser::NO,
			])
			->andWhere(['LIKE', 'whu.login_token', "{$token}%", false])
			->all();
	}

	/**
	 * @inheritdoc
	 *
	 * Returns the canonical UUID string (never the raw BINARY(16) value), so the id
	 * stored in sessions, RBAC assignments and blameable columns stays printable.
	 */
	public function getId()
	{
		return static::hasUuidPrimaryKey() ? $this->getUuid() : $this->getPrimaryKey();
	}

	/**
	 * @inheritdoc
	 */
	public function getAuthKey()
	{
		return $this->auth_key;
	}

	/**
	 * @inheritdoc
	 */
	public function validateAuthKey($authKey)
	{
		return $this->getAuthKey() === $authKey;
	}

	/**
	 * Validates password.
	 *
	 * @param string $password password to validate
	 * @return bool if password provided is valid for current user
	 */
	public function validatePassword($password)
	{
		return Yii::$app->security->validatePassword($password, $this->password_hash);
	}

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

	/**
	 * Rotates the bearer token and pushes the new value into every workspace copy.
	 *
	 * `auth_key` does double duty: Yii validates the "remember me" identity cookie
	 * against it, and the REST API accepts it as a bearer token
	 * (see [[findIdentityByAccessToken()]]). Rotating it therefore ends both at once,
	 * which is what logging out is supposed to mean — before this, signing out left the
	 * API token live forever and anyone holding it kept their access.
	 *
	 * The tenant databases carry their own copy of the row, so a rotation that stopped
	 * at master would leave the old key working there.
	 *
	 * @return bool whether the new key was stored
	 */
	public function rotateAuthKey()
	{
		$this->generateAuthKey();
		if (!$this->save(false, ['auth_key'])) {
			return false;
		}

		$this->syncAuthKey();

		return true;
	}

	/**
	 * Copies the current auth_key to every workspace_has_user row of the user and to the
	 * user row inside each workspace database.
	 *
	 * Every path that changes the key on master must reach the workspaces, or the old key
	 * keeps working there as a bearer token and "remember me" cookie. A password change
	 * calls [[syncCredentials()]] instead, which carries the key along.
	 *
	 * @return bool whether every copy was updated; a failure is logged and does not throw
	 */
	public function syncAuthKey()
	{
		return $this->syncToWorkspaces(['auth_key']);
	}

	/**
	 * Copies the current password hash and auth_key to every workspace copy of the user.
	 *
	 * The workspaces sign users in against their own copy, so a password changed only on
	 * master would leave the old one working there.
	 *
	 * @return bool whether every copy was updated; a failure is logged and does not throw
	 */
	public function syncCredentials()
	{
		return $this->syncToWorkspaces(['auth_key', 'password_hash']);
	}

	/**
	 * Writes the given attributes of this user to workspace_has_user and to the user row
	 * inside each workspace database.
	 *
	 * @param string[] $attributes
	 * @return bool whether every copy was updated
	 */
	protected function syncToWorkspaces(array $attributes)
	{
		$values = [];
		foreach ($attributes as $attribute) {
			$values[$attribute] = $this->getAttribute($attribute);
		}

		$synced = true;
		foreach (WorkspaceHasUser::find()->andWhere(['user_id' => $this->id])->all() as $workspaceUser) {
			$workspaceUser->setAttributes($values, false);
			$workspaceUser->save(false, $attributes);

			if (($workspace = Workspace::findOne(['id' => $workspaceUser->workspace_id]))) {
				try {
					$workspace->getWorkspaceDb()->createCommand()
						->update(static::tableName(), $values, ['id' => $this->id])
						->execute();
				} catch (\Exception $e) {
					// A tenant database that is down must not keep the user signed in here;
					// its copy is refreshed on the next password change or login.
					Yii::warning('Could not sync ' . implode(', ', $attributes) . " to workspace {$workspaceUser->workspace_id}: " . $e->getMessage(), __METHOD__);
					$synced = false;
				}
			}
		}

		return $synced;
	}

	/**
	 * Generates "remember me" authentication key.
	 *
	 * @throws \yii\base\Exception
	 */
	public function generateAuthKey()
	{
		$this->auth_key = Yii::$app->security->generateRandomString();
	}

	/**
	 * Gets the password reset token without timestamp.
	 *
	 * @return string|null
	 */
	public function getPasswordResetToken()
	{
		if ($this->password_reset_token) {
			return substr($this->password_reset_token, 0, strrpos($this->password_reset_token, '_'));
		}
		return null;
	}

	/**
	 * Gets the signup token without timestamp.
	 *
	 * @return string|null
	 */
	public function getSignupToken()
	{
		if ($this->signup_token) {
			return substr($this->signup_token, 0, strrpos($this->signup_token, '_'));
		}
		return null;
	}

	/**
	 * Gets the login token without timestamp.
	 *
	 * @return string|null
	 */
	public function getLoginToken()
	{
		if ($this->login_token) {
			return substr($this->login_token, 0, strrpos($this->login_token, '_'));
		}
		return null;
	}
	//endregion

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCompanies()
	{
		return $this->hasMany(Company::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getEventLogs()
	{
		return $this->hasMany(EventLog::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSettings()
	{
		return $this->hasMany(Setting::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriber()
	{
		return $this->hasOne(Subscriber::class, ['user_id' => 'id']);
	}

	/**
	 * The affiliate row of this user (docs/afiliere.md). Being an affiliate is this row,
	 * not an RBAC role; see [[getIsAffiliate()]].
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAffiliate()
	{
		return $this->hasOne(Affiliate::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionHasUsers()
	{
		return $this->hasMany(SubscriptionHasUser::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getSubscriptions()
	{
		return $this->hasMany(Subscription::class, ['id' => 'subscription_id'])->viaTable('{{%subscription_has_user}}', ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTickets()
	{
		return $this->hasMany(SupportTicket::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(User::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUsers()
	{
		return $this->hasMany(User::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUserHasNotifications()
	{
		return $this->hasMany(UserHasNotification::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getNotifications()
	{
		return $this->hasMany(Notification::class, ['id' => 'notification_id'])->viaTable('{{%user_has_notification}}', ['user_id' => 'id']);
	}

	/**
	 * Gets the AuthAssignment role name.
	 * It must return array if the user can have multiple assignments (like multiple roles)
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAuthAssignment()
	{
		return $this->hasOne(AuthAssignment::class, ['user_id' => 'id']);
	}

	/**
	 * Gets the MarketingRecipient related model.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMarketingRecipient()
	{
		return $this->hasOne(MarketingRecipient::class, ['email' => 'email']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaceHasUsers()
	{
		return $this->hasMany(WorkspaceHasUser::class, ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaces()
	{
		return $this->hasMany(Workspace::class, ['id' => 'workspace_id'])->viaTable('{{%workspace_has_user}}', ['user_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Gets the fullName.
	 *
	 * @return string
	 */
	public function getFullName()
	{
		return implode(' ', array_filter([
			$this->first_name,
			$this->middle_name,
			$this->last_name,
		]));
	}

	/**
	 * Gets the fullNameInitials.
	 *
	 * @return string
	 */
	public function getFullNameInitials()
	{
		return implode('', array_filter([
			substr($this->first_name, 0, 1),
			substr($this->last_name, 0, 1),
		]));
	}

	/**
	 * Gets the shortName.
	 *
	 * @return string
	 */
	public function getShortName()
	{
		return implode('. ', array_filter([
			substr($this->first_name, 0, 1),
			$this->last_name,
		]));
	}

	/**
	 * Gets the imageUrl with fallback to a blank image.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/user/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Checks if this user has a subscriber model associated.
	 *
	 * @param bool|int $status Flag that indicates if the status of Subscriber model should be checked.
	 * @param bool|int $deleted Flag that indicates if Subscriber model soft delete should be checked.
	 * @return bool
	 */
	public function getIsSubscriber($status = true, $deleted = true)
	{
		if (!$this->subscriber) {
			return false;
		}
		$result = true;

		if ($status !== false) {
			$result = $result && ($this->subscriber->status == ($status === true ? Subscriber::STATUS_ACTIVE : $status));
		}
		if ($deleted !== false) {
			$result = $result && ($this->subscriber->deleted == ($deleted === true ? Subscriber::NO : $deleted));
		}

		return $result;
	}

	/**
	 * Checks if this user is an affiliate: an active, non-deleted affiliate row by default
	 * (the gate of the affiliate pages in the account area).
	 *
	 * @param bool $activeOnly false accepts an inactive (suspended) affiliate too, e.g. to
	 * still show them their history
	 * @return bool
	 */
	public function getIsAffiliate($activeOnly = true)
	{
		$affiliate = $this->affiliate;
		if (!$affiliate || (int) $affiliate->deleted === Affiliate::YES) {
			return false;
		}
		return !$activeOnly || (int) $affiliate->status === Affiliate::STATUS_ACTIVE;
	}

	/**
	 * Checks if this user has at leas one permission / role associated.
	 *
	 * @return bool
	 */
	public function getHasPermissions()
	{
		return $this->authAssignment !== null;
	}

	/**
	 * Model accountActivation labels.
	 *
	 * @return array
	 */
	public static function getAccountActivationLabels()
	{
		return [
			static::ACCOUNT_ACTIVATION_AUTOMATIC => Yii::t('label', 'Automatic'),
			static::ACCOUNT_ACTIVATION_CONFIRMATION => Yii::t('label', 'Confirmation'),
		];
	}

	/**
	 * Finds all active records.
	 *
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllUsers()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return User::find()
					->alias('u')
					->select([
						'u.id',
						'u.first_name',
						'u.middle_name',
						'u.last_name',
					])
					->andWhere([
						'u.status' => static::STATUS_ACTIVE,
						'u.deleted' => static::NO,
					])
					->indexBy('id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Finds all User models that can receive notifications.
	 *
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllNotificationUsers()
	{
		return static::find()
			->alias('u')
			->joinWith([
				'authAssignment aa' => function (ActiveQuery $query) {
					$query->andWhere(['IS NOT', 'aa.item_name', null]);
				},
				'authAssignment.itemName.authItemChildren aic' => function (ActiveQuery $query) {
					$query->andWhere(['aic.child' => 'viewNotification']);
				},
			], false)
			->andWhere([
				'u.status' => static::STATUS_ACTIVE,
				'u.deleted' => static::NO,
			])
			->indexBy('id')
			->all();
	}

	/**
	 * Finds all existing users with no role or permission associated.
	 *
	 * @param string $username
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllUsersWithoutRoleByUsername($username)
	{
		return static::find()
			->alias('u')
			->select([
				'u.id',
				'u.email',
				'u.phone',
				'u.first_name',
				'u.middle_name',
				'u.last_name',
				'u.gender',
				'u.status',
			])
			->joinWith([
				'authAssignment aa' => function (\yii\db\ActiveQuery $query) {
					$query->andWhere(['IS', 'aa.item_name', null]);
				},
			], false)
			->andWhere([
				'=', 'u.email', $username
			])
			->all();
	}

	/**
	 * Finds all existing users that are not subscribers.
	 *
	 * @param string $username
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAllNotSubscriberUsersByUsername($username)
	{
		return static::find()
			->alias('u')
			->select([
				'u.id',
				'u.email',
				'u.phone',
				'u.first_name',
				'u.middle_name',
				'u.last_name',
				'u.gender',
				'u.status',
			])
			->joinWith([
				'authAssignment aa' => function (\yii\db\ActiveQuery $query) {
					$query->andWhere(new \yii\db\Expression('IF([[aa.item_name]] = "superAdmin", 1, 0) = 0'));
				},
				'subscriber s' => function (\yii\db\ActiveQuery $query) {
					$query->andWhere(['IS', 's.id', null]);
				},
			], false)
			->andWhere([
				'=', 'u.email', $username
			])
			->all();
	}
}
