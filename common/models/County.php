<?php

namespace common\models;

use Yii;
use yii\caching\TagDependency;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%county}}".
 *
 * @property int $id
 * @property string $iso_alpha2 Two-letter county code (ISO 3166-1 alpha-2)
 * @property string $name County name
 * @property string $country_code
 * @property int $status
 * @property int $deleted
 */
class County extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%county}}';
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
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['iso_alpha2', 'name', 'country_code', 'status'], 'required'],
			[['status', 'deleted'], 'integer'],
			[['iso_alpha2', 'country_code'], 'string', 'max' => 2],
			[['name'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'iso_alpha2' => Yii::t('label', 'Iso Alpha2'),
			'name' => Yii::t('label', 'Name'),
			'country_code' => Yii::t('label', 'Country Code'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * Finds all active records.
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public static function findAllCounties()
	{
		return static::getDb()->cache(function ($db) {
			return static::findAll([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			]);
		}, 0, new TagDependency(['tags' => __FUNCTION__]));
	}

	public static function findByCriteria($criteria, $params = [], $asArray = false)
	{
		$query = static::find()
			->andWhere([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->andWhere(['LIKE', 'name', $criteria . '%', false]);

		if (!empty($params)) {
			$query->andWhere($params);
		}

		return $query->orderBy(['name' => SORT_ASC])
			->asArray($asArray)
			->indexBy('id')
			->all();
	}

	public static function queryCountiesByCountry($country)
	{
		try {
			return static::find()
				->alias('c')
				->select([
					"c.name AS id",
					"c.name",
				])
				->andWhere([
					'c.country_code' => $country,
				])
				->createCommand()
				->queryAll();
		} catch (\Exception $e) {
			return [];
		}
	}

	public static function getCountyCode($criteria)
	{
		$model = static::find()
			->where([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->andWhere([
				'OR',
				['=', 'iso_alpha2', $criteria],
				['=', 'name', $criteria]
			])
			->one();
		return $model->iso_alpha2;
	}
}
