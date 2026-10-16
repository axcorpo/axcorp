<?php
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\SurveyQuestion */
/* @var $language common\models\Language */

use yii\helpers\Html;
use tws\widgets\tinymce\TinyMCE;
?>

<?= $form->field($model, "content[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->textInput() ?>

<?= $form->field($model, "answer[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->widget(TinyMCE::class) ?>
