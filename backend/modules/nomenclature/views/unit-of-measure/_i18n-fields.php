<?php
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\UnitOfMeasure */
/* @var $language common\models\Language */

use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
?>

<div class="row">
	<div class="col-sm-6">
		<?= $form->field($model, "name[{$language->language_id}]", [
			'options' => [
				'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
			],
		])->textInput() ?>
	</div>
	<div class="col-sm-6">
		<?= $form->field($model, "symbol[{$language->language_id}]", [
			'options' => [
				'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
			],
		])->textInput() ?>
	</div>
</div>
