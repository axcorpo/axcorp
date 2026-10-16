<?php
/* @var $this yii\web\View */
/* @var $affiliate common\models\Affiliate */
/* @var $user common\models\User|null */
/* @var $company common\models\Company|null */
/* @var $reviewUrl string|null the admin page of the application */

use yii\helpers\Html;

$rows = [
	Yii::t('label', 'Company') => $company ? $company->name : null,
	Yii::t('label', 'Tax Identification Number') => $company ? $company->tin : null,
	Yii::t('label', 'Registration Number') => $company ? $company->registration_number : null,
	Yii::t('label', 'Name') => $user ? $user->getFullName() : null,
	Yii::t('label', 'Email') => $user ? $user->email : null,
	Yii::t('label', 'Phone') => $user ? $user->phone : null,
];
?>

<h3><?= Html::encode(Yii::t('common', 'A company has applied to become an affiliate of {0}.', [Yii::$app->name])) ?></h3>

<table style="width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="5">
	<?php foreach ($rows as $label => $value): ?>
		<tr>
			<th style="width: 160px; vertical-align: top; text-align: left;"><?= $label ?></th>
			<td style="width: auto; vertical-align: top;"><?= $value !== null && $value !== '' ? Html::encode($value) : '&mdash;' ?></td>
		</tr>
	<?php endforeach; ?>
	<tr>
		<th style="width: 160px; vertical-align: top; text-align: left;"><?= Yii::t('label', 'Application Message') ?></th>
		<td style="width: auto; vertical-align: top;"><?= $affiliate->application_message ? nl2br(Html::encode($affiliate->application_message)) : '&mdash;' ?></td>
	</tr>
</table>

<?php if ($reviewUrl): ?>
	<p><?= Html::a(Yii::t('common', 'Review the application'), $reviewUrl) ?></p>
<?php endif; ?>
