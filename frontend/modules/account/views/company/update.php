<?php
/* @var $this yii\web\View */
/* @var $model common\models\Company */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Company')]);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
