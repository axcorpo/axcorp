<?php

/* @var $this yii\web\View */
/* @var $model common\models\Workspace */

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Workspace')]);
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
	[
		'visible' => Yii::$app->user->can('createWorkspace'),
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
	[
		'visible' => Yii::$app->user->can('updateWorkspace'),
		'tag' => 'a',
		'url' => ['regenerate-api-token', 'id' => $model->id],
		'icon' => 'fa fa-key',
		'options' => [
			'class' => 'btn btn-sm btn-warning',
			'title' => Yii::t('common', 'Regenerate API Token'),
			'data' => [
				'toggle' => 'tooltip',
				'confirm' => Yii::t('common', 'Regenerate the API token? The current token stops working immediately and the tenant configuration will be rewritten with the new one.'),
				'method' => 'POST',
			],
		],
	],
];
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>

