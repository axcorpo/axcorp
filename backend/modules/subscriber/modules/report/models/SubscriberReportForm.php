<?php

namespace backend\modules\subscriber\modules\report\models;

use common\helpers\DateHelper;
use common\models\Country;
use common\models\Package;
use common\models\Subscriber;
use Yii;
use yii\base\Model;

class SubscriberReportForm extends Model
{
	/**
	 * @var string The start date filter.
	 */
	public $from;

	/**
	 * @var string The end date filter.
	 */
	public $to;

	/**
	 * @var int The gender filter.
	 */
	public $gender;

	/**
	 * @var string The locality filter.
	 */
	public $locality;

	/**
	 * @var string The country filter.
	 */
	public $country;

	/**
	 * @var \yii\db\ActiveQuery The query to be used for this model.
	 */
	public $query;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Subscriber::find()
			->alias('s')
			->select([
				's.id',
			])
			->joinWith([
				'user u',
			])
			->andWhere([
				's.status' => Package::STATUS_ACTIVE,
				's.deleted' => Package::NO,
			])
			->groupBy([
				's.id',
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['from', 'to'], 'safe'],
			[['gender'], 'integer'],
			['gender', 'in', 'range' => [1, 2]],
			[['locality'], 'string', 'max' => 255],
			[['country'], 'exist', 'skipOnError' => true, 'targetClass' => Country::class, 'targetAttribute' => ['country' => 'iso_alpha2']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'from' => Yii::t('label', 'From'),
			'to' => Yii::t('label', 'To'),
			'gender' => Yii::t('label', 'Gender'),
			'locality' => Yii::t('label', 'Locality'),
			'country' => Yii::t('label', 'Country'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function formName()
	{
		return '';
	}

	/**
	 * Gets the report dataset.
	 *
	 * @return array
	 */
	public function getDataset()
	{
		try {
			$this->query->andFilterWhere(['>=', 's.created_at', DateHelper::formatAsDateTime($this->from)]);
			$this->query->andFilterWhere(['<=', 's.created_at', DateHelper::formatAsDateTime($this->to)]);
			$this->query->andFilterWhere(['LIKE', 's.locality', $this->locality]);
			$this->query->andFilterWhere([
				'u.gender' => $this->gender,
				's.country' => $this->country,
			]);

			return [
				[
					'label' => Yii::t('common', 'Subscribers'),
					'data' => $this->query->count(),
				],
			];
		} catch (\Exception $e) {
			return [];
		}
	}
}
