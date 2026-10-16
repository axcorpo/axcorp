<?php

namespace backend\modules\subscriber\models;

use common\helpers\DateHelper;
use common\helpers\StringHelper;
use common\models\Subscriber;
use common\models\Subscription;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class SubscriberSearch extends DataTableAction
{
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
				's.user_id',
				's.code',
                's.notes',
				's.created_at',
				's.status',
			])
			->joinWith([
				'user u',
				'user.authAssignment aa' => function (ActiveQuery $query) {
					$query->andWhere(new \yii\db\Expression('IF([[aa.item_name]] = "superAdmin", 1, 0) = 0'));
				},
				'subscriptions sub',
				'subscriptions.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition(['pt.language_id' => Yii::$app->language]);
				},
			])
			->andWhere(['!=', 's.user_id', Yii::$app->user->id])
			->andWhere([
				's.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Subscriber::NO,
			]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Subscriber::class => [
				'id',
				'action' => function (Subscriber $model) {
					$actions = [];

					if ($this->requestParams['deleted'] == Subscriber::YES) {
						if (Yii::$app->user->can('restoreSubscriber')) {
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
						if (Yii::$app->user->can('deleteSubscriber')) {
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
						if (Yii::$app->user->can('viewSubscriber')) {
							$actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
								'class' => 'btn btn-xs btn-info btn-slide-center-v',
								'title' => Yii::t('common', 'View'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('updateSubscriber')) {
							$actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
								'class' => 'action-update btn btn-xs btn-primary',
								'title' => Yii::t('common', 'Update'),
								'data' => [
									'toggle' => 'tooltip',
								],
							]);
						}
						if (Yii::$app->user->can('deleteSubscriber')) {
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
						if (Yii::$app->user->can('sendSubscriberResource')) {
							$actions[] = Html::a('<span class="fa fa-send"></span>', ['send-email-resource', 'id' => $model->id], [
								'class' => 'action-delete btn btn-xs btn-default',
								'title' => Yii::t('common', 'Send Resources Via Email'),
								'data' => [
									'toggle' => 'tooltip',
									'popup-action' => '',
								],
							]);
						}
					}

					return implode('', $actions);
				},
                'notes' => function (Subscriber $model) {
                    $content = [];
                    if ($model->notes) {
                        $innerContent = [
                            Html::tag('span', null, ['class' => 'card-icon fa fa-sticky-note-o', 'style' => 'margin-right: 10px;']),
                            Html::tag('span', StringHelper::truncate(strip_tags($model->notes), 120), ['class' => 'card-title']),
                        ];
                        $content[] = '<span style="max-width: 300px;">' . Html::a(implode('', $innerContent), ['update-subscriber-notes', 'id' => $model->id], [
                                'class' => 'card card-outline card-block btn-default',
                                'data' => [
                                    'toggle' => 'tooltip',
                                    'popup-action' => '',
                                    'popup-done' => ['redrawDataTable' => '#dt-subscribers'],
                                ],
                            ]) . '</span>';
                    } else {
                        $innerContent = [
                            Html::tag('span', null, ['class' => 'card-icon fa fa-sticky-note']),
                            Html::tag('span', null, ['class' => 'card-title']),
                        ];
                        $content[] = Html::a(implode('', $innerContent), ['update-subscriber-notes', 'id' => $model->id], [
                            'class' => 'card card-outline card-horizontal btn-default',
                            'style' => 'display: inline-flex;',
                            'data' => [
                                'popup-action' => '',
                                'popup-done' => ['redrawDataTable' => '#dt-subscribers'],
                            ],
                        ]);
                    }
                    return implode('', $content);
                },
				'code' => function (Subscriber $model) {
					return $model->code ? Html::tag('code', $model->code) : '&mdash;';
				},
				'image' => function (Subscriber $model) {
					if ($model->user->image && is_file(Yii::getAlias("@uploads/user/{$model->user->id}/{$model->user->image}"))) {
						$imgTag = Html::img($model->user->getImageUrl(), [
							'class' => 'img-responsive',
							'alt' => $model->user->getFullName(),
						]);
						return Html::a($imgTag, $model->user->getImageUrl(), [
							'title' => Yii::t('common', 'Open Gallery'),
							'data' => [
								'toggle' => 'tooltip',
								'fancybox' => 'users',
								'caption' => $model->user->getFullName(),
							],
						]);
					}
					return '&mdash;';
				},
				'name' => function (Subscriber $model) {
					return $model->user ? $model->user->getFullName() : '&mdash;';
				},
				'email' => function (Subscriber $model) {
					return $model->user ? $model->user->email : '&mdash;';
				},
				'phone' => function (Subscriber $model) {
					return $model->user ? $model->user->phone : '&mdash;';
				},
				'subscriptions' => function (Subscriber $model) {
					if ($model->subscriptions) {
						$content = [];
						foreach ($model->subscriptions as $subscription) {
							$status = Subscription::getStatusLabels()[$subscription->status];
							$status = Html::tag('span', $status['label'], ['class' => 'text-' . $status['color']]);
							$startAt = $subscription->start_at ? Yii::$app->formatter->asDatetime($subscription->start_at) : '&mdash;';
							$endAt = $subscription->end_at ? Yii::$app->formatter->asDatetime($subscription->end_at) : '&mdash;';
							$featuresIcon = '';
							if (!empty($subscription->parent_id)) {
								$featuresIcon = Html::tag('span', null, [
									'class' => 'fa fa-puzzle-piece',
									'title' => Yii::t('common', 'Features'),
									'data' => [
										'toggle' => 'tooltip',
									],
								]);
							}

							$content[] = Html::beginTag('div', ['class' => 'item-adjacent-spacing bordered-top']);
							$content[] = Html::tag('div', "{$subscription->formattedName} $featuresIcon &mdash; {$status}");
							if ($subscription->type == Subscription::TYPE_FREE) {
								$content[] = Html::tag('div', Yii::t('label', 'Start At') . ": {$startAt}");
							} else {
								$content[] = Html::tag('div', Yii::t('label', 'Next Due At'). ": {$endAt}");
							}
							$content[] = Html::endTag('div');
						}
						return implode('', $content);
					}
					return '&mdash;';
				},
				'created_at' => function (Subscriber $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
				'last_activity' => function (Subscriber $model) {
					return $model->user->last_activity ? Yii::$app->formatter->asDatetime($model->user->last_activity) : '&mdash;';
				},
				'status' => function (Subscriber $model) {
					$status = Subscriber::getStatusLabels()[$model->status];
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
				case 'name':
					$query->$filterOperator([
						'OR',
						['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.middle_name, u.last_name)') , $value],
						['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.last_name)') , $value],
						['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.last_name)') , $value],
					]);
					break;
				case 'email':
					$query->$filterOperator(['LIKE', 'u.email', $value]);
					break;
				case 'phone':
					$query->$filterOperator(['LIKE', 'u.phone', $value]);
					break;
				case 'subscriptions':
					$query->$filterOperator([
						'OR',
						['LIKE', 'sub.code', $value],
						['LIKE', 'pt.name', $value],
					]);
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 's.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'last_activity':
					$query->$filterOperator(['LIKE', 'u.last_activity', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 's.' . $column['data'], $value]);
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
				case 'name':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.middle_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				case 'email':
					$query->addOrderBy(['u.email' => $sort]);
					break;
				case 'phone':
					$query->addOrderBy(['u.phone' => $sort]);
					break;
				case 'subscriptions':
					$query->addOrderBy(['pt.name' => $sort]);
					break;
				case 'last_activity':
					$query->addOrderBy(['u.last_activity' => $sort]);
					break;
				default:
					$query->addOrderBy(['s.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
