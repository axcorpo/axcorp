<?php

namespace common\models;

use Yii;
use yii\caching\TagDependency;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%currency}}".
 *
 * @property int $id
 * @property string $iso_code
 * @property string $symbol
 * @property string $name
 * @property int $status
 * @property int $deleted
 */
class Currency extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%currency}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
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
			[['iso_code', 'status'], 'required'],
			[['status', 'deleted'], 'integer'],
			[['iso_code'], 'string', 'max' => 3],
			[['symbol', 'name'], 'string', 'max' => 255],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'iso_code' => Yii::t('label', 'Iso Code'),
			'symbol' => Yii::t('label', 'Symbol'),
			'name' => Yii::t('label', 'Name'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * Gets the formattedName.
	 *
	 * @return string
	 */
	public function getFormattedName()
	{
		return "{$this->iso_code} ({$this->symbol})";
	}

	/**
	 * Finds all active records.
	 *
	 * @return array|self[]
	 */
	public static function findAllCurrencies()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->where([
						'status' => self::STATUS_ACTIVE,
						'deleted' => self::NO,
					])
					->orderBy(['name' => SORT_ASC])
					->indexBy('iso_code')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}
}
