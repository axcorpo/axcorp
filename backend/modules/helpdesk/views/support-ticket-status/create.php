<?php

/* @var $this yii\web\View */
/* @var $model common\models\SupportTicketStatus */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Support Ticket Status')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Helpdesk'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Support Ticket Statuses'),
		'url' => ['index'],
	],
	Yii::t('common', 'Create'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewHelpdeskSupportTicketStatus'),
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
