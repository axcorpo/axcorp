<?php

namespace backend\modules\subscriber\modules\report\models;

use common\helpers\DateHelper;
use common\models\Package;
use common\models\Subscription;
use Yii;
use yii\base\Model;

class SubscriptionStatusReportForm extends Model
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

		$this->query = Subscription::find()
			->alias('sub')
			->select([
				'sub.id',
				'sub.status',
				'COUNT([[sub.id]]) AS [[total]]',
			])
			->andWhere([
				'sub.deleted' => Package::NO,
			])
			->groupBy([
				'sub.status',
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

			$models = $this->query->indexBy('status')->asArray()->all();
			$data = [];

			foreach (Subscription::getStatusLabels() as $statusKey => $statusValue) {
				$data[] = [
					'label' => $statusValue['label'],
					'data' => $models[$statusKey]['total'] ?: 0,
					'color' => $statusValue['hexColor'],
				];
			}

			return $data;
		} catch (\Exception $e) {
			return [];
		}
	}
}
