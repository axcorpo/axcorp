<?php

/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\Tutorial */
/* @var $language common\models\Language */

use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
?>

<?= $form->field($model, "title[{$language->language_id}]", [
	'options' => [
		'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
	],
])->textInput() ?>
<?= $form->field($model, "content[{$language->language_id}]")->textarea(['rows' => 6])->widget(TinyMCE::class) ?>
