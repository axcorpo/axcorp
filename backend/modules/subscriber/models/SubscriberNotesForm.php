<?php

namespace backend\modules\subscriber\models;

use common\models\Subscriber;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class SubscriberNotesForm extends Subscriber
{
    /**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['notes'], 'trim'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'notes' => Yii::t('label', 'Internal Notes'),
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
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 * @throws \yii\db\Exception
	 */
    public function save($runValidation = true, $attributeNames = null)
	{
		$transaction = static::getDb()->beginTransaction();
		try {
            if (!parent::save($runValidation, ['notes'])) {
                throw new \Exception();
            }
			$transaction->commit();
			return $this;
		} catch(\Exception $e) {
			$transaction->rollBack();
			return false;
		}
	}
}
