<?php

namespace backend\modules\subscriber\modules\report\models;

use common\helpers\DateHelper;
use common\models\Package;
use common\models\Workspace;
use Yii;
use yii\base\Model;

class WorkspaceTypeReportForm extends Model
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

		$this->query = Workspace::find()
			->alias('w')
			->select([
				'w.id',
				'w.type',
				'COUNT([[w.id]]) AS [[total]]',
			])
			->andWhere([
				'w.deleted' => Package::NO,
			])
			->groupBy([
				'w.type',
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

			$models = $this->query->indexBy('type')->asArray()->all();
			$data = [];

			foreach (Workspace::getTypeLabels() as $key => $value) {
				$data[] = [
					'label' => $value,
					'data' => $models[$key]['total'] ?: 0,
				];
			}

			return $data;
		} catch (\Exception $e) {
			return [];
		}
	}
}
