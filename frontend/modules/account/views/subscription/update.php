<?php
/* @var $this yii\web\View */
/* @var $model common\models\Subscription */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Subscription')]);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
