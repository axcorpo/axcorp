<?php
/* @var $this yii\web\View */
/* @var $model common\models\Workspace */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Workspace')]);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>
