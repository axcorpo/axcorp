<?php

/* @var $this yii\web\View */

use backend\modules\affiliate\models\PayoutSearch;

$this->title = Yii::t('common', 'Affiliate Payouts');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['affiliate/index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => true,
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
		'visible' => true,
		'content' => $this->render('/_export', [
			'dataTable' => '#dt-payouts',
			'model' => PayoutSearch::class,
			'title' => $this->title,
		]),
	],
];
?>

<?= $this->render('_grid', [
	'id' => 'dt-payouts',
]) ?>
