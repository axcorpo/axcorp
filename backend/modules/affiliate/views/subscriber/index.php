<?php

/* @var $this yii\web\View */

use backend\modules\affiliate\models\SubscriberSearch;

$this->title = Yii::t('common', 'Referred Subscribers');
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
		'content' => $this->render('/_export', [
			'dataTable' => '#dt-subscribers',
			'model' => SubscriberSearch::class,
			'title' => $this->title,
		]),
	],
];
?>

<?= $this->render('_grid', [
	'id' => 'dt-subscribers',
]) ?>
