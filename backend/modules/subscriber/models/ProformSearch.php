<?php

namespace backend\modules\subscriber\models;

use common\helpers\DateHelper;
use common\models\Invoice;
use common\models\Item;
use common\models\PackageTranslation;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class ProformSearch extends DataTableAction
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->query = Invoice::find()
            ->alias('i')
            ->select([
                'i.id',
                'i.document_series',
                'i.document_number',
                'i.issued_at',
                'i.paid_at',
                'i.amount',
                'i.details',
                'i.currency',
                'i.status',
                'i.external_id',
                'sub.code AS subscription_code',
                'pt.name AS package_name',
            ])
            ->joinWith([
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
                'i.status' => Invoice::STATUS_UNPAID,
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
