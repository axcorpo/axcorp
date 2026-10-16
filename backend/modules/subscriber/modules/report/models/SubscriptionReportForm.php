<?php

namespace backend\modules\subscriber\modules\report\models;

use common\helpers\DateHelper;
use common\models\Package;
use Yii;
use yii\base\Model;

class SubscriptionReportForm extends Model
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
	 * @var \yii\db\ActiveQuery The query to be used for this model.
	 */
	public $query;


	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Package::find()
			->alias('p')
			->select([
				'p.id',
				'COUNT([[sub.id]]) AS [[total]]',
			])
			->joinWith([
				'subscriptions sub',
			])
			->andWhere([
				'p.status' => Package::STATUS_ACTIVE,
				'p.deleted' => Package::NO,
			])
			->groupBy([
				'p.id',
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['from', 'to'], 'safe'],
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
			$this->query->andFilterWhere(['>=', 'sub.created_at', DateHelper::formatAsDateTime($this->from)]);
			$this->query->andFilterWhere(['<=', 'sub.created_at', DateHelper::formatAsDateTime($this->to)]);

			$models = $this->query->indexBy('id')->asArray()->all();
			$data = [];

			foreach (Package::findPaidPackages() as $package) {
				$data[] = [
					'label' => $package->translation->name,
					'data' => $models[$package->id]['total'] ?: 0,
				];
			}

			return $data;
		} catch (\Exception $e) {
			return [];
		}
	}
}
