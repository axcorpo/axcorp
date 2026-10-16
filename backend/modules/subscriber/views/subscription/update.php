<?php

/* @var $this yii\web\View */
/* @var $model common\models\Subscription */

use common\models\Subscription;
use common\models\User;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\web\JsExpression;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Subscription')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Subscriber'),
		'url' => ['subscriber/update', 'id' => $model->subscriber_id],
	],
	[
		'label' => Yii::t('common', 'Subscriptions'),
		'url' => ['subscriber/update', 'id' => $model->subscriber_id],
	],
	Yii::t('common', 'Update'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewSubscriber'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'List'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createSubscriber'),
		'tag' => 'a',
		'url' => ['create'],
		'icon' => 'fa fa-plus',
		'options' => [
			'class' => 'btn btn-sm btn-success',
			'title' => Yii::t('common', 'Create'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
