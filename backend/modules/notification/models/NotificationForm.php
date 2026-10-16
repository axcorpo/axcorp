<?php

namespace backend\modules\notification\models;

use common\models\Notification;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class NotificationForm extends Notification
{
	/**
	 * @var array The users that should get this notification.
	 */
	public $targeted_users = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->type = static::TYPE_USER;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['title', 'message'], 'trim'],
			[['targeted_users'], 'each', 'rule' => ['exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['targeted_users' => 'id']]],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'targeted_users' => Yii::t('label', 'Targeted Users'),
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

		// Expose the FKs as canonical UUID strings so the dropdown (whose option
		// keys are UUID strings) can match the selected values.
		$this->targeted_users = ArrayHelper::getColumn($this->users, 'uuid');
	}

	/**
	 * Links the User models.
	 *
	 * @return bool
	 */
	protected function saveUsers()
	{
		try {
			$this->unlinkAll('users', true);

			$users = User::findAll([
				'id' => $this->targeted_users,
				'status' => User::STATUS_ACTIVE,
				'deleted' => User::NO,
			]);

			foreach ($users as $user) {
				$this->link('users', $user);
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveUsers()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
