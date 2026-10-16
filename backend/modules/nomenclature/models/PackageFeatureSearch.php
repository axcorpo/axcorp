<?php

namespace backend\modules\nomenclature\models;

use common\models\Feature;
use common\models\PackageFeature;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class PackageFeatureSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = PackageFeature::find()
			->alias('pf')
			->select([
				'pf.id',
				'pf.package_id',
				'pf.feature_id',
				'pf.name',
				'pf.value',
			])
			->andWhere([
				'pf.package_id' => isset($this->requestParams['package_id']) ? $this->requestParams['package_id'] : null,
				'pf.deleted' => PackageFeature::NO,
			])
			->orderBy([
				'pf.id' => SORT_ASC,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			PackageFeature::class => [
				'id',
				'action' => function (PackageFeature $model) {
					$actions = [];

					if (Yii::$app->user->can('updatePackage')) {
						$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update-package-feature', 'id' => $model->id], [
							'class' => 'action-update btn btn-xs btn-primary',
							'title' => Yii::t('common', 'Update'),
							'data' => [
								'toggle' => 'tooltip',
								'popup-action' => '',
								'popup-done' => ['redrawDataTable' => '#dt-package-features'],
							],
						]);
						$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete-package-feature', 'id' => $model->id], [
							'class' => 'action-delete btn btn-xs btn-danger',
							'title' => Yii::t('common', 'Delete'),
							'data' => [
								'toggle' => 'tooltip',
								'dt-operation' => 'delete',
								'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							],
						]);
					}

					$actions = array_map(function ($actionsChunk) {
						return Html::tag('div', implode('', $actionsChunk));
					}, array_chunk($actions, 3));

					return implode('', $actions);
				},
				'name' => function (PackageFeature $model) {
					return Feature::getFeatureLabels()[$model->name] ?? ($model->name ?: '&mdash;');
				},
				'value' => function (PackageFeature $model) {
					return $model->value ?: 0;
				},
			],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function applyFilter(ActiveQuery $query, $columns, $search)
	{
		return $query;
	}

	/**
	 * @inheritdoc
	 */
	public function applyOrder(ActiveQuery $query, $columns, $order)
	{
		return $query;
	}
}
