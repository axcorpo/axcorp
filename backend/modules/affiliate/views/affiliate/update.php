<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\AffiliateForm */

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Affiliate')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['index'],
	],
	[
		'label' => $model->code,
		'url' => ['view', 'id' => $model->uuid],
	],
	Yii::t('common', 'Update'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewAffiliate'),
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
		'visible' => Yii::$app->user->can('viewAffiliate'),
		'tag' => 'a',
		'url' => ['view', 'id' => $model->uuid],
		'icon' => 'fa fa-eye',
		'options' => [
			'class' => 'btn btn-sm btn-info',
			'title' => Yii::t('common', 'View'),
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
