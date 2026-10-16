<?php

namespace backend\modules\nomenclature\models;

use common\models\DocumentSeries;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class DocumentSeriesForm extends DocumentSeries
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->first_number = 1;
		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			['name', 'unique'],
			[['type'], 'required'],
			[['type'], 'in', 'range' => array_keys(static::getTypeLabels())],
			[['first_number'], 'integer', 'min' => 1],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
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
	 * Saves the model.
	 *
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
