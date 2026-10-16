<?php

/* @var $this yii\web\View */
/* @var $model common\models\Workspace */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Workspace')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Workspaces'),
		'url' => ['workspace/index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewWorkspace'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'Workspaces'),
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
