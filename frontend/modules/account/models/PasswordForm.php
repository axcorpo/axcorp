<?php

namespace frontend\modules\account\models;

use common\models\User;
use common\models\Workspace;
use common\models\WorkspaceHasUser;
use common\validators\PasswordValidator;
use Yii;
use yii\base\Model;

/**
 * Password change form for the signed-in user.
 *
 * Kept apart from [[ProfileForm]] so that editing personal data never asks for
 * the current password: re-authentication belongs to the password change alone.
 */
class PasswordForm extends Model
{
	/**
	 * @var string The new password.
	 */
	public $new_password;

	/**
	 * @var string The new password confirmation.
	 */
	public $new_password_confirm;

	/**
	 * @var string The current password, required in order to change it.
	 */
	public $current_password;

	/**
	 * @var User The account whose password is being changed.
	 */
	private $_user;

	/**
	 * @param User $user
	 * @param array $config
	 */
	public function __construct(User $user, $config = [])
	{
		$this->_user = $user;
		parent::__construct($config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['new_password', 'new_password_confirm', 'current_password'], 'required'],
			[['new_password', 'new_password_confirm', 'current_password'], 'string', 'max' => 255],
			['new_password', PasswordValidator::class],
			['new_password_confirm', 'compare', 'compareAttribute' => 'new_password', 'message' => Yii::t('common', 'Passwords don\'t match.')],
			// Changing a password is a re-authentication point: without it, temporary
			// access to a signed-in session converts straight into permanent control
			// of the account.
			['current_password', 'validateCurrentPassword'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'new_password' => Yii::t('label', 'New Password'),
			'new_password_confirm' => Yii::t('label', 'Confirm New Password'),
			'current_password' => Yii::t('label', 'Current Password'),
		];
	}

	/**
	 * Gets the User model.
	 *
	 * @return User
	 */
	public function getUser()
	{
		return $this->_user;
	}

	/**
	 * Verifies the current password. Inline validator for `current_password`.
	 *
	 * @param string $attribute
	 * @param array $params
	 */
	public function validateCurrentPassword($attribute, $params)
	{
		if ($this->hasErrors($attribute)) {
			return;
		}
		if (!$this->_user->validatePassword((string) $this->current_password)) {
			$this->addError($attribute, Yii::t('common', 'The provided credentials are invalid.'));
		}
	}

	/**
	 * Changes the password and rotates the auth key, in master and in every
	 * workspace copy of the account.
	 *
	 * @return bool
	 */
	public function changePassword()
	{
		if (!$this->validate()) {
			return false;
		}

		$user = $this->_user;
		$dbTransaction = User::getDb()->beginTransaction();
		try {
			$user->setPassword($this->new_password);
			$user->generateAuthKey();
			if (!$user->save(false, ['password_hash', 'auth_key'])) {
				throw new \Exception();
			}

			$workspaceUsers = WorkspaceHasUser::find()
				->andWhere(['user_id' => $user->id])
				->all();
			foreach ($workspaceUsers as $workspaceUser) {
				$workspaceUser->auth_key = $user->auth_key;
				$workspaceUser->password_hash = $user->password_hash;
				if (!$workspaceUser->save(false)) {
					throw new \Exception();
				}
				$workspace = Workspace::findOne(['id' => $workspaceUser->workspace_id]);
				$workspace->getWorkspaceDb()->createCommand()->update('{{%user}}', [
					'auth_key' => $user->auth_key,
					'password_hash' => $user->password_hash,
				], [
					'id' => $user->id,
				])->execute();
			}

			$dbTransaction->commit();
			return true;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$this->addError('new_password', Yii::t('common', 'Cannot reset password for this user.'));
			return false;
		}
	}
}
