<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Partner */
/* @var $language common\models\Language */

use yii\helpers\Html;
use tws\widgets\tinymce\TinyMCE;
?>

<?= $form->field($model, "description[{$language->language_id}]")->widget(TinyMCE::class) ?>
