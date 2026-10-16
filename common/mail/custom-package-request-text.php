<?php
/* @var $this yii\web\View */
/* @var $user common\models\User */
/* @var $message string */
?>

<?= Yii::t('label', 'From') ?>: <?= $user->fullName ?: '&mdash;' ?>,
<?= Yii::t('label', 'Email') ?>: <?= $user->email ?: '&mdash;' ?>,
<?= Yii::t('label', 'Phone') ?>: <?= $user->phone ?: '&mdash;' ?>,
<?= Yii::t('label', 'Message') ?>: <?= $message ?: '&mdash;' ?>
