<?php
/* @var $this yii\web\View */

use common\widgets\Alert;
use kartik\dialog\Dialog;
?>

<?= Alert::widget([
	'options' => [
		'class' => 'alert-icon',
	],
]) ?>

<?= Dialog::widget([
	'id' => 'application-dialog',
	'libName' => 'appDialog',
	'useNative' => false,
	'showDraggable' => true,
	'overrideYiiConfirm' => true,
	'jsPosition' => \yii\web\View::POS_END,
	'dialogDefaults' => [
		Dialog::DIALOG_ALERT => [
			'closable' => false,
			'draggable' => true,
			'type' => Dialog::TYPE_INFO,
			'title' => Yii::t('common', 'Info'),
			'buttonLabel' => '<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('common', 'Ok')
		],
		Dialog::DIALOG_CONFIRM => [
			'closable' => false,
			'draggable' => false,
			'type' => Dialog::TYPE_WARNING,
			'title' => Yii::t('common', 'Confirmation'),
			'btnOKClass' => 'btn-warning btn-slide-right',
			'btnOKLabel' => '<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('common', 'Yes'),
			'btnCancelClass' => 'btn btn-light btn-slide-right',
			'btnCancelLabel' => '<span class="glyphicon glyphicon-ban-circle"></span> ' . Yii::t('common', 'No'),
		],
		Dialog::DIALOG_PROMPT => [
			'closable' => false,
			'draggable' => false,
			'title' => Yii::t('common', 'Info'),
			'buttons' => [
				[
					'icon' => 'glyphicon glyphicon-ban-circle',
					'label' => Yii::t('common', 'Cancel'),
				],
				[
					'icon' => 'glyphicon glyphicon-ok',
					'label' => Yii::t('common', 'Ok'),
					'cssClass' => 'btn-primary btn-slide-right',
				],
			],
		],
		Dialog::DIALOG_OTHER => [
			'closable' => true,
			'draggable' => false,
			'type' => Dialog::TYPE_SUCCESS,
			'title' => Yii::t('common', 'Info'),
			'buttons' => [
				[
					'icon' => 'glyphicon glyphicon-ban-circle',
					'label' => Yii::t('common', 'Cancel'),
				],
				[
					'icon' => 'glyphicon glyphicon-ok',
					'label' => Yii::t('common', 'Ok'),
					'cssClass' => 'btn-default',
				],
			],
		],
	],
]) ?>
