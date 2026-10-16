<?php

namespace backend\modules\subscriber\models;

use common\helpers\DateHelper;
use common\models\Integration;
use common\models\Invoice;
use common\models\Item;
use common\models\PackageTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\bootstrap\Dropdown;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class InvoiceSearch extends DataTableAction
{

	protected $integration;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$currentDate = date('Y-m-d H:i:s');
		$this->integration = Integration::find()
			->where([
				'type' => Integration::TYPE_SPV,
				'status' => Integration::STATUS_ACTIVE,
				'deleted' => Integration::NO,
			])
			->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[expire_at]]) > 0"))
			->one();
		$this->query = Invoice::find()
			->alias('i')
			->select([
				'i.id',
				'i.company_id',
				'i.document_series',
				'i.document_number',
				'i.issued_at',
				'i.paid_at',
				'i.amount',
				'i.currency',
				'i.details',
				'i.status',
				'i.external_id',
				'i.e_invoice_upload_id',
				'i.e_invoice_download_id',
				'i.e_invoice_status',
				'i.e_invoice_sent_at',
				'i.e_invoice_error',
				'sub.code AS subscription_code',
				'pt.name AS package_name',
			])
			->joinWith([
				'company c',
				'items itm' => function (ActiveQuery $query) {
					$query->andWhere([
						'itm.deleted' => Item::NO,
					]);
				},
				'items.subscription sub',
				'items.subscription.package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
				'items.subscription.subscriber.user u' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'u.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere([
                'i.deleted' => isset($this->requestParams['deleted']) ? $this->requestParams['deleted'] : Invoice::NO,
			])
            ->andWhere([
                'i.status' => Invoice::STATUS_PAID,
            ]);
	}

	/**
	 * @inheritdoc
	 * @throws \yii\db\Exception
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
        return ArrayHelper::toArray($query->all(), [
            Invoice::class => [
                'id',
                'action' => function (Invoice $model) {
                    $actions = [];

                    if ($this->requestParams['deleted'] == Invoice::YES) {
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
                        if (Yii::$app->user->can('viewInvoice') && empty($model->external_id)) {
                            $actions[] = Html::a('<span class="fa fa-cloud-upload"></span>', ['send-to-oblio', 'id' => $model->id], [
                                'class' => 'action-send-oblio btn btn-xs btn-warning',
                                'title' => Yii::t('common', 'Send to Oblio'),
                                'data' => [
                                    'toggle' => 'tooltip',
                                    'dt-operation' => 'send-to-oblio',
                                    'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
                                ],
                            ]);
                        }
                        if (Yii::$app->user->can('viewInvoice')) {
                            $viewClass = !empty($model->external_id) ? 'btn btn-xs purple-sharp btn-slide-center-v' : 'btn btn-xs btn-info btn-slide-center-v';
                            $actions[] = Html::a('<span class="fa fa-eye"></span>', ['view', 'id' => $model->id], [
                                'class' => $viewClass,
                                'title' => Yii::t('common', 'View'),
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
	                    if (!empty($model->company)) {
		                    if (Yii::$app->user->can('updateInvoice') && ((!in_array($model->e_invoice_status, [Invoice::E_STATUS_PROCESSED])) || empty($model->e_invoice_upload_id) || empty($model->e_invoice_download_id))) {
			                    $actions[] = Html::a('<span class="fa fa-edit"></span>', ['update', 'id' => $model->id], [
				                    'class' => 'action-update btn btn-xs btn-primary',
				                    'title' => Yii::t('common', 'Update'),
				                    'data' => [
					                    'toggle' => 'tooltip',
				                    ],
			                    ]);
		                    }

		                    $dropdown = [];
		                    $color = Invoice::getEStatusLabels()[$model->e_invoice_status]['color'];
		                    $items = [];
		                    $items[] = [
			                    'label' => '<span class="fa fa-code"></span> ' . Yii::t('backend', 'Generate XML for e-Invoice'),
			                    'url' => ['e-invoice', 'id' => $model['id'], 'action' => 'generate'],
			                    'linkOptions' => [
				                    'class' => 'dropdown-item btn-default',
				                    'data-prevent-page-overlay' => "true",
				                    'data-toggle' => 'tooltip',
				                    'title' => Yii::t('backend', 'The document will be downloaded in the e-Invoice XML format. It will not be automatically sent to the RO e-Invoice portal: after downloading, please upload the file to the Private Virtual Space (SPV).')
			                    ],
		                    ];
		                    if (!empty($this->integration)) {
			                    if (in_array($model->e_invoice_status, [Invoice::E_STATUS_NOT_SENT, Invoice::E_STATUS_UNPROCESSED, Invoice::E_STATUS_REJECTED])) {
				                    $items[] = [
					                    'label' => '<span class="fa fa-upload"></span> ' . Yii::t('backend', 'Send the e-Invoice'),
					                    'url' => ['e-invoice', 'id' => $model->id, 'action' => 'upload'],
					                    'linkOptions' => [
						                    'class' => 'dropdown-item btn-default',
						                    'data-prevent-page-overlay' => "true",
						                    'data-toggle' => 'tooltip',
						                    'title' => Yii::t('backend', 'Send the electronic invoice to the RO e-Invoice portal')
					                    ],
				                    ];
			                    }
			                    if (in_array($model->e_invoice_status, [Invoice::E_STATUS_SENT, Invoice::E_STATUS_PENDING])) {
				                    $items[] = [
					                    'label' => '<span class="fa fa-question"></span> ' . Yii::t('backend', 'Verify e-Invoice'),
					                    'url' => ['e-invoice', 'id' => $model->id, 'action' => 'verify'],
					                    'linkOptions' => [
						                    'class' => 'dropdown-item btn-default',
						                    'data-prevent-page-overlay' => "true",
						                    'data-toggle' => 'tooltip',
						                    'title' => Yii::t('backend', 'Verify e-Invoice')
					                    ],
				                    ];
			                    }
			                    if (in_array($model->e_invoice_status, [Invoice::E_STATUS_PROCESSED, Invoice::E_STATUS_UNPROCESSED]) && !empty($model->e_invoice_download_id)) {
				                    $items[] = [
					                    'label' => '<span class="fa fa-download"></span> ' . Yii::t('backend', 'Validated e-Invoice'),
					                    'url' => ['e-invoice', 'id' => $model->id, 'action' => 'download'],
					                    'linkOptions' => [
						                    'class' => 'dropdown-item btn-default',
						                    'data-prevent-page-overlay' => "true",
						                    'data-toggle' => 'tooltip',
						                    'title' => Yii::t('backend', 'Download the e-Invoice with index {index}, validated and signed by SPV on {date}.', ['index' => $model->e_invoice_upload_id, 'date' => Yii::$app->formatter->asDate($model->e_invoice_sent_at)]),
					                    ],
				                    ];
			                    }
		                    }
		                    $dropdown[] = Html::beginTag('div', ['class' => 'btn-group e-invoice']);
		                    $dropdown[] = Html::button('<span class="fa fa-internet-explorer" data-toggle="tooltip" title="' . Yii::t('common', 'e-Invoice') . ' (' . Invoice::getEStatusLabels()[$model->e_invoice_status]['label'] . ')"></span>', [
			                    'style' => 'margin-left: 5px;',
			                    'class' => "btn btn-xs btn-{$color} dropdown-toggle",
			                    'data-toggle' => 'dropdown',
			                    'title' => Yii::t('common', 'e-Invoice')
		                    ]);
		                    $dropdown[] = Dropdown::widget([
			                    'items' => $items,
			                    'encodeLabels' => false,
		                    ]);
		                    $dropdown[] = Html::endTag('div');
		                    $actions[] = implode('', $dropdown);
	                    }
                    }

                    return implode('', $actions);
                },
                'document_number' => function (Invoice $model) {
                    return $model->document_series . ' ' . $model->document_number ?: '&mdash;';
                },
                'subscription' => function (Invoice $model) {
                    if ($model->items[0]->subscription){
                        return '#'.$model->items[0]->subscription->code . ' (' . $model->items[0]->subscription->package->translation->name . ')';
                    } else {
                        foreach ($model->getItems()->active()->deleted(false)->each() as $item) {
                            $itemDetails = $item->getUnserializedValue('details');
                        }
                        return $itemDetails['subscription'];
                    }
                },
                'amount' => function (Invoice $model) {
                    return Yii::$app->formatter->asCurrency($model->amount) ?: '&mdash;';
                },
                'subscriber' => function (Invoice $model) {
                    if ($model->items[0]->subscription->subscriber->user->fullName) {
                        return $model->items[0]->subscription->subscriber->user->fullName ?: '&mdash;';
                    } else {
                        $details = $model->getUnserializedValue('details');
                        $client = $details['client'];
                        return $client['name'];
                    }
                },
                'issued_at' => function (Invoice $model) {
                    return Yii::$app->formatter->asDatetime($model->issued_at) ?: '&mdash;';
                },
                'paid_at' => function (Invoice $model) {
                    return Yii::$app->formatter->asDatetime($model->paid_at) ?: '&mdash;';
                },
	            'e_invoice_upload_id' => function (Invoice $model) {
		            return $model->e_invoice_upload_id ?: '&mdash;';
	            },
	            'e_invoice_download_id' => function (Invoice $model) {
		            return $model->e_invoice_download_id ?: '&mdash;';
	            },
	            'e_invoice_error' => function (Invoice $model) {
		            return $model->e_invoice_error ? nl2br($model->e_invoice_error): '&mdash;';
	            },
	            'e_invoice_status' => function (Invoice $model) {
		            $status = Invoice::getEStatusLabels()[$model->e_invoice_status];
		            return Html::tag('span', $status['label'], ['class' => 'label label-block label-' . $status['color']]);
	            },
                'status' => function (Invoice $model) {
                    $status = Invoice::getStatusLabels()[$model->status];
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
				case 'document_number':
					$query->$filterOperator([
						'OR',
						['LIKE', 'i.document_series', $value],
						['LIKE', 'i.document_number', $value],
						['LIKE', new Expression('CONCAT_WS(" ", [[i.document_series]], LPAD([[i.document_number]], 5, 0))'), $value],
					]);
					break;
				case 'subscription':
					$query->$filterOperator([
						'OR',
						['LIKE', 'sub.code', $value],
						['LIKE', 'pt.name', $value],
						['LIKE', 'itm.details', $value],
					]);
					break;
				case 'subscriber':
					$query->$filterOperator([
						'OR',
                        ['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.middle_name, u.last_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.last_name, u.middle_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.last_name, u.middle_name, u.first_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.last_name, u.first_name, u.middle_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.first_name, u.last_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.last_name, u.first_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.last_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.first_name, u.middle_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.last_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.middle_name, u.first_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.last_name, u.first_name)') , $value],
                        ['LIKE', new Expression('CONCAT_WS(" ", u.last_name, u.middle_name)') , $value],
                        ['LIKE', 'i.details', $value],
                    ]);
					break;
				case 'issued_at':
					$query->$filterOperator(['LIKE', 'i.issued_at', DateHelper::formatAsDate($value)]);
					break;
				case 'due_at':
					$query->$filterOperator(['LIKE', 'i.due_at', DateHelper::formatAsDate($value)]);
					break;
				case 'paid_at':
					$query->$filterOperator(['LIKE', 'i.paid_at', DateHelper::formatAsDate($value)]);
					break;
				default:
					if (array_key_exists($column['data'], $schema)) {
						$query->$filterOperator(['LIKE', 'i.' . $column['data'], $value]);
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
				case 'document_number':
					$query->addOrderBy([
						'i.document_series' => $sort,
						'i.document_number' => $sort,
					]);
					break;
				case 'subscription':
					$query->addOrderBy(['sub.code' => $sort]);
					break;
				case 'subscriber':
					$query->addOrderBy([
						'u.first_name' => $sort,
						'u.middle_name' => $sort,
						'u.last_name' => $sort,
					]);
					break;
				default:
					$query->addOrderBy(['i.' . $column['data'] => $sort]);
					break;
			}
		}
		return $query;
	}
}
