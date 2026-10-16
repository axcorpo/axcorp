<?php

/* @var $this yii\web\View */
/* @var $model common\models\Notification */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Notification')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Notifications'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
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
