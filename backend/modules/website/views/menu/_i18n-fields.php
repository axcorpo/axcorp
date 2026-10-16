<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Menu */
/* @var $language common\models\Language */

use yii\helpers\Html;
use tws\widgets\tinymce\TinyMCE;
use kartik\select2\Select2;
?>

<?= $form->field($model, "name[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->textInput() ?>


