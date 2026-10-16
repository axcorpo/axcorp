<?php
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\CarouselItem */
/* @var $language common\models\Language */

use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
?>

<?= $form->field($model, "title[{$language->language_id}]")->textInput() ?>

<?= $form->field($model, "content[{$language->language_id}]")->widget(TinyMCE::class) ?>

<div class="margin-top-15 margin-bottom-10 bold"><?= Yii::t('label', 'Call to Action Button') ?></div>
<div class="row">
	<div class="col-sm-6">
		<?= $form->field($model, "anchor[{$language->language_id}]")->textInput() ?>
	</div>
	<div class="col-sm-6">
		<?= $form->field($model, "url[{$language->language_id}]")->input('url') ?>
	</div>
</div>
