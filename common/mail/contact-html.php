<?php
/* @var $this yii\web\View */
/* @var $model frontend\models\ContactForm */

use yii\helpers\Html;
?>

<h3><?= Yii::t('common', 'This is a message from {0} contact form.', [Yii::$app->name]) ?></h3>

<table style="width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="5">
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Name') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->name ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Email') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->email ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Phone') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->phone ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Message') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->message ? nl2br($model->message) : '&mdash;' ?></td>
	</tr>
</table>
