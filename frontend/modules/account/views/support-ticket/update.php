<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */
/* @var $supportTicketCommentModel common\models\SupportTicketComment */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Support Ticket')]) . " (#{$model->getDocumentSeriesNumber()}) - {$model->subject}";
?>

<?= $this->render('_form', [
	'model' => $model,
	'supportTicketCommentModel' => $supportTicketCommentModel,
]) ?>
