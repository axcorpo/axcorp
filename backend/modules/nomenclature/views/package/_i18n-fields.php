<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Package */
/* @var $language common\models\Language */

use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
?>

<?= $form->field($model, "name[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->textInput() ?>

<?= $form->field($model, "content[{$language->language_id}]")->widget(TinyMCE::class) ?>
