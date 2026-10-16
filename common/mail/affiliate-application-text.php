<?php
/* @var $this yii\web\View */
/* @var $affiliate common\models\Affiliate */
/* @var $user common\models\User|null */
/* @var $company common\models\Company|null */
/* @var $reviewUrl string|null the admin page of the application */
?>

<?= Yii::t('common', 'A company has applied to become an affiliate of {0}.', [Yii::$app->name]) ?>

<?= Yii::t('label', 'Company') ?>: <?= $company ? $company->name : '-' ?>,
<?= Yii::t('label', 'Tax Identification Number') ?>: <?= $company && $company->tin ? $company->tin : '-' ?>,
<?= Yii::t('label', 'Name') ?>: <?= $user ? $user->getFullName() : '-' ?>,
<?= Yii::t('label', 'Email') ?>: <?= $user && $user->email ? $user->email : '-' ?>,
<?= Yii::t('label', 'Phone') ?>: <?= $user && $user->phone ? $user->phone : '-' ?>,
<?= Yii::t('label', 'Application Message') ?>: <?= $affiliate->application_message ?: '-' ?>
<?php if ($reviewUrl): ?>


<?= Yii::t('common', 'Review the application') ?>: <?= $reviewUrl ?>
<?php endif; ?>
