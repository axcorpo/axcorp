<?php
/* @var $this yii\web\View */
/* @var $model common\models\SupportTicket */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Support Ticket')]);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
