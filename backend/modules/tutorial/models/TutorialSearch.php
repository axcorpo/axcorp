<?php

namespace backend\modules\tutorial\models;

use common\helpers\DateHelper;
use common\models\Tutorial;
use common\models\TutorialCategoryTranslation;
use common\models\TutorialTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class TutorialSearch extends DataTableAction
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		// Set the query
		$this->query = Tutorial::find()
			->alias('t')
			->select([
				't.id',
				't.image',
				't.file',
				't.sort_order',
				't.created_at',
				't.status'
			])
			->joinWith([
				'tutorialTranslations tt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'tt.language_id' => Yii::$app->language,
						'tt.deleted' => TutorialTranslation::NO,
					]);
				},
                'tutorialCategories.tutorialCategoryTranslations tct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'tct.language_id' => Yii::$app->language,
                        'tct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
			])
			->andWhere([
				't.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Tutorial::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Tutorial::class => [
				'id',
				'action' => function (Tutorial $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Tutorial::YES) {
						if (Yii::$app->user->can('restoreTutorial')) {
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
						if (Yii::$app->user->can('deleteTutorial')) {
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
						if (Yii::$app->user->can('viewTutorial')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'action-view btn btn-xs btn-info',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateTutorial')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteTutorial')) {
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
				'image' => function (Tutorial $model) {
		            if ($model->image && is_file(Yii::getAlias("@uploads/tutorial/{$model->uuid}/{$model->image}"))) {
                        $imgTag = Html::img($model->imageUrl, [
                            'class' => 'img-responsive',
                            'alt' => $model->translation->title,
                        ]);
                        return Html::a($imgTag, $model->imageUrl, [
                            'title' => Yii::t('common', 'Open Gallery'),
                            'data' => [
                                'toggle' => 'tooltip',
                                'fancybox' => 'tutorials',
                                'caption' => $model->translation->title,
                            ],
                        ]);
                    }
		            return '';
				},
                'file' => function (Tutorial $model) {
                    if ($model->file && is_file(Yii::getAlias("@uploads/tutorial/{$model->uuid}/{$model->file}"))) {
                        if (in_array(strtolower(end(explode('.', $model->file))), ['mp4'])) {
                            return '<video style="width: 200px;" controls>
                                        <source src="' .$model->fileUrl . '" type="video/mp4">
                                    </video>';
                        } elseif (in_array(strtolower(end(explode('.', $model->file))), ['jpeg', 'jpg', 'png', 'gif', 'svg'])) {
                            $imgTag = Html::img($model->fileUrl, [
                                'class' => 'img-responsive',
                                'alt' => $model->translation->title,
                            ]);
                            return Html::a($imgTag, $model->imageUrl, [
                                'title' => Yii::t('common', 'Open Gallery'),
                                'data' => [
                                    'toggle' => 'tooltip',
                                    'fancybox' => 'files',
                                    'caption' => $model->translation->title,
                                ],
                            ]);
                        } else {
                            return Html::a($model->translation->title, $model->fileUrl, ['target' => '_blank']);
                        }
                    }
                    return '&mdash;';
                },
				'title' => function (Tutorial $model) {
					return Html::encode($model->translation->title);
				},
                'category' => function (Tutorial $model) {
                    if ($model->tutorialCategories) {
                        return implode(', ', ArrayHelper::getColumn($model->tutorialCategories, 'translation.title'));
                    }
                    return '&mdash;';
                },
				'sort_order' => function (Tutorial $model) {
					return $model->sort_order ?: '&mdash;';
				},
                'created_at' => function (Tutorial $model) {
                    return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
                },
				'status' => function (Tutorial $model) {
					$status = Tutorial::getStatusLabels()[$model->status];
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
		// Loop through the DataTable columns
		foreach ($columns as $column) {
			// Continue if the column is not searchable
			if ($column['searchable'] == 'false') {
				continue;
			}
			// Get the filter value
			if (!empty($search['value'])) {
				$value = $search['value'];
				$filterOperator = 'orFilterWhere';
			} else {
				$value = $column['search']['value'];
				$filterOperator = 'andFilterWhere';
			}
			// Handle custom column filter
			switch ($column['data']) {
				case 'title':
					$query->$filterOperator(['LIKE', 'tt.title', $value]);
					break;
                case 'category':
                    $query->$filterOperator(['LIKE', 'tct.title', $value]);
                    break;
                case 'created_at':
                    $query->$filterOperator(['LIKE', 'tt.created_at', DateHelper::formatAsDate($value)]);
                    break;
				default:
					// Apply default filter if column exist in table schema
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
		// Loop through the DataTable order items
		foreach ($order as $key => $item) {
			// Get the order targeted column
			$column = $columns[$item['column']];
			// Continue if the column is not orderable
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			// Get the order value
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;
			// Handle custom column filter
			switch ($column['data']) {
				case 'title':
					$query->addOrderBy(['tt.title' => $sort]);
					break;
                case 'category':
                    $query->addOrderBy(['tct.title' => $sort]);
                    break;
				default:
					// Apply default order
					$query->addOrderBy(['t.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
