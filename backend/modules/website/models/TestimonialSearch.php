<?php

namespace backend\modules\website\models;

use common\helpers\DateHelper;
use common\models\Testimonial;
use common\models\User;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class TestimonialSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Testimonial::find()
			->alias('t')
			->select([
				't.id',
				't.image',
				't.name',
				't.organization',
				't.rating',
				't.ip_address',
				't.updated_by',
				't.created_at',
				't.updated_at',
				't.status',
			])
			->joinWith([
				'testimonialTranslations tt' => function (ActiveQuery $query) {
					$query->andOnCondition(['tt.language_id' => Yii::$app->language]);
				},
				'updater up' => function (ActiveQuery $query) {
					$query->select([
						'up.id',
						'up.first_name',
						'up.middle_name',
						'up.last_name',
					]);
					$query->andOnCondition(['up.deleted' => User::NO]);
				},
			])
			->andWhere([
				't.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Testimonial::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Testimonial::class => [
				'id',
				'action' => function (Testimonial $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Testimonial::YES) {
						if (Yii::$app->user->can('restoreTestimonial')) {
							$actions[] = Html::a('<span class="fa fa-undo"></span>', ['restore', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-success',
								'title' => Yii::t('common', 'Restore'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'restore',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
						if (Yii::$app->user->can('deleteTestimonial')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-danger',
								'title' => Yii::t('common', 'Delete Permanently'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'delete-permanently',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
					} else {
						if (Yii::$app->user->can('viewTestimonial')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateTestimonial')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteTestimonial')) {
							$actions[] = Html::a('<span class="fa fa-trash"></span>', ['delete', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-danger',
								'title' => Yii::t('common', 'Delete'),
								'data' => [
									'toggle' => 'tooltip',
									'dt-operation' => 'delete',
									'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]);
						}
					}

					$actions = array_map(function ($actionsChunk) {
						return Html::tag('div', implode('', $actionsChunk));
					}, array_chunk($actions, 3));

					return implode('', $actions);
				},
				'image' => function (Testimonial $model) {
					if ($model->image && is_file(Yii::getAlias("@uploads/testimonial/{$model->uuid}/{$model->image}"))) {
						$imgTag = Html::img($model->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->name,
						]);
						return Html::a($imgTag, $model->getImageUrl(), [
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'testimonials',
								'caption' => $model->name,
							],
						]);
					}
					return '&mdash;';
				},
				'name' => function (Testimonial $model) {
					return $model->name ?: '&mdash;';
				},
				'role' => function (Testimonial $model) {
					return $model->translation->role ?: '&mdash;';
				},
				'organization' => function (Testimonial $model) {
					return $model->organization ?: '&mdash;';
				},
				'rating' => function (Testimonial $model) {
					return $model->rating ?: 0;
				},
				'updated_by' => function (Testimonial $model) {
					return $model->updater ? $model->updater->fullName : '&mdash;';
				},
				'created_at' => function (Testimonial $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'updated_at' => function (Testimonial $model) {
					return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
				},
				'status' => function (Testimonial $model) {
					$status = Testimonial::getStatusLabels()[$model->status];
					return Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);
				},
			],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function applyFilter(ActiveQuery $query, $columns, $search)
	{
		/** @var \yii\db\ActiveRecord $modelClass */
		$modelClass = $query->modelClass;
		$schema = $modelClass::getTableSchema()->columns;

		foreach ($columns as $column) {
			if ($column['searchable'] == 'false') {
				continue;
			}
			if (!empty($search['value'])) {
				$value = trim($search['value']);
				$filterOperator = 'orFilterWhere';
			} else {
				$value = trim($column['search']['value']);
				$filterOperator = 'andFilterWhere';
			}

			switch ($column['data']) {
				case 'role':
					$query->$filterOperator(['LIKE', 'tt.role', $value]);
					break;
				case 'rating':
					$query->$filterOperator(['=', 't.rating', $value]);
					break;
				case 'updated_by':
					$query->$filterOperator([
						'OR',
						['LIKE', 'up.first_name', $value],
						['LIKE', 'up.middle_name', $value],
						['LIKE', 'up.last_name', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 't.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'updated_at':
					$query->$filterOperator(['LIKE', 't.updated_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 't.' . $column['data'], $value]);
					}
					break;
			}
		}
		return $query;
	}

	/**
	 * @inheritdoc
	 */
	public function applyOrder(ActiveQuery $query, $columns, $order)
	{
		foreach ($order as $key => $item) {
			$column = $columns[$item['column']];
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;

			switch ($column['data']) {
				case 'role':
					$query->addOrderBy(['tt.role' => $sort]);
					break;
				case 'updated_by':
					$query->addOrderBy([
						'up.first_name' => $sort,
						'up.middle_name' => $sort,
						'up.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['t.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
