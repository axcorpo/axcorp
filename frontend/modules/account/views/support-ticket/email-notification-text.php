<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */
/* @var $supportTicketCommentModel common\models\SupportTicketComment */

use common\models\SupportTicket;
use tws\helpers\Url;
?>

<?= Yii::t('common', 'Support Ticket') ?>: #<?= $model->getDocumentSeriesNumber() ?>
<?= Yii::t('label', 'Subject') ?>: <?= strip_tags($model->subject) ?: '&mdash;' ?>,
<?php if (!$supportTicketCommentModel): ?>
	<?= Yii::t('label', 'Message') ?>: <?= strip_tags($model->content) ?: '&mdash;' ?>,
<?php else: ?>
	<?= Yii::t('label', 'Message') ?>: <?= strip_tags($supportTicketCommentModel->content) ?: '&mdash;' ?>,
<?php endif; ?>
<?= Yii::t('label', 'Subscription') ?>: <?= ($model->subscription && $model->subscription->package) ? $model->subscription->formattedName : '&mdash;' ?>,
<?= Yii::t('label', 'Department') ?>: <?= $model->supportTicketDepartment ? $model->supportTicketDepartment->translation->name : '&mdash;' ?>,
<?= Yii::t('label', 'Priority') ?>: <?= $model->supportTicketPriority ? $model->supportTicketPriority->translation->name : '&mdash;' ?>,
<?= Yii::t('label', 'Status') ?>: <?= $model->supportTicketStatus ? $model->supportTicketStatus->translation->name : '&mdash;' ?>,
<?= Yii::t('label', 'From') ?>: <?= $model->creator->fullName ?: '&mdash;' ?>,
<?= Yii::t('label', 'Email') ?>: <?= $model->creator->email ?: '&mdash;' ?>,
<?= Yii::t('label', 'Phone') ?>: <?= $model->creator->phone ?: '&mdash;' ?>,

<?= Yii::t('common', 'View this on {0} dashboard', [Yii::$app->name]) ?>: <?= Url::to(["/admin/helpdesk-manager/support-ticket/{$model->uuid}/update"], true) ?>
