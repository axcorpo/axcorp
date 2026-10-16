<?php

namespace common\models;

use Yii;
use yii\base\Model;

class TwoFactorAuthenticationForm extends Model
{
	/**
	 * @var string The account activation token.
	 */
	public $token;

	/**
	 * @var string The honeypot field.
	 */
	public $verifyCode;

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
			[['token'], 'required'],
			[['token'], 'trim'],
			['verifyCode', 'safe'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'token' => Yii::t('label', 'Confirm Login Code'),
		];
	}

	/**
	 * Finds user by login token.
	 *
	 * @return array|\yii\db\ActiveRecord[]|\yii\db\ActiveRecord|User[]|User|null
	 */
	public function getUser()
	{
		if (!$this->_user) {
			$this->_user = User::findByLoginToken($this->token);
		}
		return $this->_user;
	}

	/**
	 * Confirm Login.
	 *
	 * @return bool if login was confirmed.
	 */
	public function confirmLogin()
	{
		if (!empty($this->verifyCode)) {
			return false;
		}

		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			$currentDate = date('Y-m-d H:i:s');
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
				// Remove the login token
				$user->login_token = null;
				$user->last_login = $currentDate;
				if (!$user->save(false)) {
					$this->addError('token', Yii::t('common', 'Cannot confirm login for this user.'));
					throw new \Exception();
				}
			}

			// For WorkspaceHasUser models (if any) remove the login token
			/** @var WorkspaceHasUser[] $workspaceUsers */
			$workspaceUsers = WorkspaceHasUser::find()
				->andWhere(['user_id' => $user->id])
				->andWhere(['LIKE', 'login_token', "{$this->token}%", false])
				->all();
			foreach ($workspaceUsers as $workspaceUser) {
				$workspaceUser->login_token = null;
				$workspaceUser->last_login = $currentDate;
				if (!$workspaceUser->save(false)) {
					$this->addError('username', Yii::t('common', 'Cannot confirm login for this user.'));
					throw new \Exception();
				}
				// Set login token in workspace database
				$workspaceUser->workspace->getWorkspaceDb()->createCommand()->update(User::tableName(),
					[
						'login_token' => null,
						'last_login' => $currentDate,
					],
					[
					'AND',
						['=', 'id', $user->id],
						['IS NOT', 'login_token', null],
					]
				)->execute();
			}

			$dbTransaction->commit();
			return true;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
