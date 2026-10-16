<?php

/* @var $this yii\web\View */
/* @var $model common\models\User */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Operator User')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Operator Users'),
		'url' => ['index'],
	],
	Yii::t('common', 'Create'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewUser'),
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
];
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
