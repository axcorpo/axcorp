<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */
/* @var $commentModel common\models\SupportTicketComment */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Support Ticket')]) . " (#{$model->getDocumentSeriesNumber()})";
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Helpdesk'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Support Tickets'),
		'url' => ['index'],
	],
	Yii::t('common', 'Update {item}', ['item' => "#{$model->getDocumentSeriesNumber()}"]),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewHelpdeskSupportTicket'),
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
		'visible' => Yii::$app->user->can('createHelpdeskSupportTicket'),
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

<?= $this->render('_comment-form', [
	'model' => $model,
	'commentModel' => $commentModel,
]) ?>
