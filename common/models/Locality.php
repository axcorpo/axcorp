<?php

namespace common\models;

use Yii;
use yii\caching\TagDependency;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%locality}}".
 *
 * @property int $id
 * @property string $county
 * @property string $name
 * @property string $latitude
 * @property string $longitude
 * @property int $status
 * @property int $deleted
 */
class Locality extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%locality}}';
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
			[['county', 'name', 'status'], 'required'],
			[['status', 'deleted'], 'integer'],
			[['county', 'name', 'latitude', 'longitude'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'county' => Yii::t('label', 'County'),
			'name' => Yii::t('label', 'Name'),
			'latitude' => Yii::t('label', 'Latitude'),
			'longitude' => Yii::t('label', 'Longitude'),
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
	public static function findAllLocalities()
	{
		return static::getDb()->cache(function ($db) {
			return static::findAll([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			]);
		}, 0, new TagDependency(['tags' => __FUNCTION__]));
	}

	/**
	 * Finds model by slug.
	 *
	 * @param string $criteria
	 * @param bool $asArray
	 * @return array|static[]]
	 */
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

	public static function queryLocalitiesByCounty($county)
	{
		try {
			return static::find()
				->alias('l')
				->select([
					"l.name AS id",
					"l.name",
				])
				->andWhere([
					'l.county' => $county,
				])
				->createCommand()
				->queryAll();
		} catch (\Exception $e) {
			return [];
		}
	}
}
