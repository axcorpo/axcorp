<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Testimonial */
/* @var $language common\models\Language */

use yii\helpers\Html;
use tws\widgets\tinymce\TinyMCE;
?>

<?= $form->field($model, "role[{$language->language_id}]")->textInput() ?>

<?= $form->field($model, "message[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->widget(TinyMCE::class) ?>
