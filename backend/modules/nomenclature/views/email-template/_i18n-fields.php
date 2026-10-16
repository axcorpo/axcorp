<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Template */
/* @var $language common\models\Language */

use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
?>

<?= $form->field($model, "name[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->textInput() ?>

<?= $form->field($model, "subject[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])
->textInput([
	'data' => [
		'insert-target-scope' => 'false',
	],
])
->hint(Yii::t('backend', 'Move the cursor at the desired position, then click a short code to insert it into the current template.')) ?>

<?= $form->field($model, "content[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])
->widget(TinyMCE::class)
->hint(Yii::t('backend', 'Move the cursor at the desired position, then click a short code to insert it into the current template.')) ?>

<?= $this->render('@backend/views/_shared/_shortcodes', [
	'shortCodes' => \backend\modules\nomenclature\models\EmailTemplateForm::getShortCodeItems($model->variant),
	'insertTarget' => [
		'#' . Html::getInputId($model, "subject[{$language->language_id}]"),
		'#' . Html::getInputId($model, "content[{$language->language_id}]"),
	],
]) ?>
