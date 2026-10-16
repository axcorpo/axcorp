<?php
/* @var $this yii\web\View */
/* @var $model frontend\models\ContactForm */
?>

<?= Yii::t('common', 'This is a message from {0} contact form.', [Yii::$app->name]) ?>

<?= Yii::t('label', 'Name') ?>: <?= $model->name ?: '&mdash;' ?>,
<?= Yii::t('label', 'Email') ?>: <?= $model->email ?: '&mdash;' ?>,
<?= Yii::t('label', 'Phone') ?>: <?= $model->phone ?: '&mdash;' ?>,
<?= Yii::t('label', 'Message') ?>: <?= $model->message ?: '&mdash;' ?>
