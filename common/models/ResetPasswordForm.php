<?php

namespace common\models;

use common\validators\PasswordValidator;
use common\helpers\CaptchaHelper;
use Yii;
use yii\base\Model;

class ResetPasswordForm extends Model
{
	const SCENARIO_TOKEN = 'token';
	const SCENARIO_PASSWORD = 'password';

	/**
	 * @var string The account activation token.
	 */
	public $token;

	/**
	 * @var string The new password.
	 */
	public $password;

	/**
	 * @var string The new password confirm.
	 */
	public $password_confirm;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

    /**
     * @var string The honeypot field.
     */
    public $captchaResponse;

    /**
     * @var bool Whether [[resetPassword()]] verifies the captcha. False for the REST API,
     * which presents none.
     */
    public $requireCaptcha = true;

	/**
	 * @var User The User model.
	 */
	private $_user;


	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['token', 'password', 'password_confirm'], 'required'],
			[['token', 'password', 'password_confirm'], 'trim'],
			[['password', 'password_confirm'], 'string', 'max' => 255],
			[['password'], PasswordValidator::class],
			['password_confirm', 'compare', 'compareAttribute' => 'password', 'message' => Yii::t('common', 'Passwords don\'t match.')],
			['workEmail', 'safe'],
            ['captchaResponse', 'safe'],
        ];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			// captchaResponse must be loadable, or verify() fails every submission once
			// reCAPTCHA is configured.
			self::SCENARIO_TOKEN => ['token', 'workEmail', 'captchaResponse'],
			self::SCENARIO_PASSWORD => ['password', 'password_confirm', 'workEmail', 'captchaResponse'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'token' => Yii::t('label', 'Password Reset Code'),
			'password' => Yii::t('label', 'New Password'),
			'password_confirm' => Yii::t('label', 'Confirm New Password'),
		];
	}

	/**
	 * Finds user by password reset token.
	 *
	 * @return array|\yii\db\ActiveRecord[]|\yii\db\ActiveRecord|User[]|User|null
	 */
	public function getUser()
	{
		if (!$this->_user) {
			$this->_user = User::findByPasswordResetToken($this->token);
		}
		return $this->_user;
	}

	/**
	 * Resets password.
	 *
	 * @return bool if password was reset.
	 */
	public function resetPassword()
	{
		if (!empty($this->workEmail)) {
			return false;
		}
		// The REST API drives this flow too and presents no captcha widget, so it turns
		// the check off rather than fail one it could never pass.
		if ($this->requireCaptcha && !CaptchaHelper::verify($this->captchaResponse)) {
			$this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
			return false;
		}
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			if (!($user = $this->getUser())) {
				$this->addError('token', Yii::t('yii', '{attribute} is invalid.', [
					'attribute' => $this->getAttributeLabel('token'),
				]));
				throw new \Exception();
			}

			if (is_array($user)) {
				// Use the first User model in array, since the ID is the same
				/** @var User $user */
				$user = reset($user);
			} else {
				// Set the password hash for User model and remove the password reset token
				$user->setPassword($this->password);
				// Rotate the bearer token too: a reset is the one moment we know the account
				// may have been compromised, and auth_key survives a password change on its
				// own. The loop below propagates the new key to the workspace copies.
				$user->generateAuthKey();
				$user->password_reset_token = null;
				if (!$user->save(false)) {
					$this->addError('password', Yii::t('common', 'Cannot reset password for this user.'));
					throw new \Exception();
				}
			}

			// Set the password hash for WorkspaceHasUser models (if any) and remove the password reset token
			/** @var WorkspaceHasUser[] $workspaceUsers */
			$workspaceUsers = WorkspaceHasUser::find()
				->andWhere(['user_id' => $user->id])
				->all();
			foreach ($workspaceUsers as $model) {
                $model->auth_key = $user->auth_key;
                $model->password_hash = $user->password_hash;
                $model->password_reset_token = null;
				if (!$model->save(false)) {
					$this->addError('username', Yii::t('common', 'Cannot reset password for this user.'));
					throw new \Exception();
				} else {
                    $workspace = Workspace::findOne(['id' => $model->workspace_id]);
                    $workspaceDb = $workspace->getWorkspaceDb();
                    $workspaceDb->createCommand()->update('{{%user}}', [
                        'auth_key' => $user->auth_key,
                        'password_hash' => $user->password_hash,
                        'password_reset_token' => null,
                    ],
                    [
                        'id' => $user->id
                    ])->execute();
                }
			}

			if ($user->signup_token && $user->status == User::STATUS_INACTIVE) {
				$account = new ActivateAccountForm();
				// Activated on the user's behalf, off the back of a token they proved they
				// hold; there is no form here to carry a captcha.
				$account->requireCaptcha = false;
				$token = explode('_', $user->signup_token)[0];
				$account->token = $token;
				if (!$account->activate()) {
					throw new \Exception();
				}
			}

			$dbTransaction->commit();
			return true;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
