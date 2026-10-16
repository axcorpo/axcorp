<?php

/* @var $this yii\web\View */
/* @var $data array */

use yii\helpers\Html;

$this->title = $data['title'] ?: Yii::t('common', 'Success!');
$this->params['bodyAttributes']['class'][] = 'page-success';
?>
<div class="site-success text-center">
	<div class="page-message"><?= Html::encode($data['message']) ?></div>
</div>