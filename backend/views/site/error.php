<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

use yii\helpers\Html;

$this->title = $name;
$this->params['bodyAttributes']['class'][] = 'page-error';
?>
<div class="site-error text-center">
	<div class="page-message"><?= Html::encode($exception->getMessage()) ?></div>

	<?= Html::a(Yii::t('common', 'Go To Main Page'), ['/site/index'], ['class' => 'btn btn-lg red mt-15']) ?>
</div>