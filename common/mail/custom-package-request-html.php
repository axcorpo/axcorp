<?php
/* @var $this yii\web\View */
/* @var $user common\models\User */
/* @var $message string */

use yii\helpers\Html;
?>

<table style="width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="5">
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'From') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $user->fullName ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Email') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $user->email ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Phone') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $user->phone ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 100px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Message') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $message ? nl2br($message) : '&mdash;' ?></td>
	</tr>
</table>
