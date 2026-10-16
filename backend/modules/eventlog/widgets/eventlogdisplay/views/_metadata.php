<?php

/* @var $this yii\web\View */
/* @var $model \common\models\EventLog */
/* @var $content string */

use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;

$model = $this->context->model;
?>

<?= GridView::widget([
	'dataProvider' => new ArrayDataProvider([
		'allModels' => [$model],
	]),
	'options' => [
		'class' => 'eventlogdisplay-metadata table-responsive mb-15',
	],
	'tableOptions' => [
		'class' => 'table table-condensed',
	],
	'summary' => '',
	'columns' => [
		[
			'format' => 'raw',
			'label' => Yii::t('label', 'User'),
			'value' => function ($model) {
				return Html::a($model->user->fullName, ['/user-manager/user/view', 'id' => $model->user_id], ['target' => '_blank']);
			},
		],
		[
			'format' => 'html',
			'label' => Yii::t('label', 'Operation'),
			'attribute' => 'formattedOperation',
		],
		[
			'visible' => !empty($model->resource),
			'label' => Yii::t('label', 'Resource'),
			'attribute' => 'translatedResource',
		],
		[
			'format' => 'datetime',
			'label' => Yii::t('label', 'Date'),
			'attribute' => 'created_at',
		],
		[
			'label' => Yii::t('label', 'IP Address'),
			'attribute' => 'ip_address',
		],
	],
]) ?>