<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */
/* @var $supportTicketCommentModel common\models\SupportTicketComment */

use common\models\SupportTicket;
use tws\helpers\Url;
?>

<table style="width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="5">
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('common', 'Support Ticket') ?></th>
		<td style="width: auto; vertical-align: top;">#<?= $model->getDocumentSeriesNumber() ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Subject') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->subject ?: '&mdash;' ?></td>
	</tr>
	<?php if (!$supportTicketCommentModel): ?>
		<tr>
			<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Message') ?></th>
			<td style="width: auto; vertical-align: top;"><?= $model->content ? nl2br($model->content) : '&mdash;' ?></td>
		</tr>
	<?php else: ?>
		<tr>
			<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Message') ?></th>
			<td style="width: auto; vertical-align: top;"><?= $supportTicketCommentModel->content ? nl2br($supportTicketCommentModel->content) : '&mdash;' ?></td>
		</tr>
	<?php endif; ?>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Subscription') ?></th>
		<td style="width: auto; vertical-align: top;"><?= ($model->subscription && $model->subscription->package) ? $model->subscription->formattedName : '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Department') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->supportTicketDepartment ? $model->supportTicketDepartment->translation->name : '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Priority') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->supportTicketPriority ? $model->supportTicketPriority->translation->name : '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Status') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->supportTicketStatus ? $model->supportTicketStatus->translation->name : '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'From') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->creator->fullName ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Email') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->creator->email ?: '&mdash;' ?></td>
	</tr>
	<tr>
		<th style="width: 150px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Phone') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $model->creator->phone ?: '&mdash;' ?></td>
	</tr>
</table>

<p>
	<a href="<?= Url::to(["/admin/helpdesk-manager/support-ticket/{$model->uuid}/update"], true) ?>"><?= Yii::t('common', 'View this on {0} dashboard', [Yii::$app->name]) ?></a>
</p>
