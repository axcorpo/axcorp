<?php

namespace backend\modules\subscriber\modules\report\models;

use common\models\Package;
use Yii;
use yii\base\Model;

class SubscriptionMonthlyReportForm extends Model
{
	/**
	 * @var int The year filter.
	 */
	public $year;

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

		$this->year = (new \DateTime)->format('Y');

		$this->query = Package::find()
			->alias('p')
			->select([
				'p.id',
				'COUNT([[sub.id]]) AS [[total]]',
				'MONTH([[sub.created_at]]) AS [[subscription_created_month]]',
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
				'subscription_created_month',
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['year'], 'integer', 'min' => 1900, 'max' => (new \DateTime)->format('Y')],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'year' => Yii::t('label', 'Year'),
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
			$this->query->andFilterWhere(['LIKE', 'sub.created_at', $this->year]);

			$models = $this->query->createCommand()->queryAll();
			$data = [];
			$labels = [];

			// Labels
			foreach ($models as $model) {
				$labels[$model['id']]['label'] = $model['name'];
				$labels[$model['id']]['total'] += (int) $model['total'];
				$labels[$model['id']]['data'] = [[0, 0], [1, 0], [2, 0], [3, 0], [4, 0], [5, 0], [6, 0], [7, 0], [8, 0], [9, 0], [10, 0], [11, 0]];
			}

			// Dataset
			foreach ($models as $model) {
				if (!empty($model['subscription_created_month'])) {
					$labels[$model['id']]['data'][$model['subscription_created_month'] - 1][1] += (int) $model['total'];
				}
			}

			// Data
			foreach (Package::findPaidPackages() as $package) {
				$data[] = [
					'label' => "{$package->translation->name} " . ($labels[$package->id]['total'] ? "({$labels[$package->id]['total']})" : "(0)"),
					'data' => $labels[$package->id]['data'] ?: 0,
				];
			}

			return $data;
		} catch (\Exception $e) {
			return [];
		}
	}
}
