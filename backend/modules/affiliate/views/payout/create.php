<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\affiliate\models\PayoutCreateForm */
/* @var $affiliate common\models\Affiliate|null */
/* @var $affiliates array uuid => label */
/* @var $summary array see AffiliatePayoutService::getPayableSummary() */

use common\models\AffiliateSettings;
use kartik\select2\Select2;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Affiliate Payout')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliate Payouts'),
		'url' => ['index'],
	],
	Yii::t('common', 'Create'),
];
$this->params['actions'] = [
	[
		'visible' => true,
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'List'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];
?>

<?= Html::beginForm(['create'], 'get', ['class' => 'mb-10']) ?>
	<div class="row">
		<div class="col-sm-6 form-group">
			<?= Html::label(Yii::t('label', 'Affiliate'), 'payout-affiliate', ['class' => 'control-label']) ?>
			<?php // Choosing an affiliate shows their payable commissions (the form is a GET). ?>
			<?= Select2::widget([
				'name' => 'affiliate_id',
				'value' => $affiliate ? $affiliate->uuid : null,
				'data' => $affiliates,
				'options' => [
					'id' => 'payout-affiliate',
				],
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
				'pluginEvents' => [
					'change' => 'function () { this.form.submit(); }',
				],
			]) ?>
		</div>
	</div>
<?= Html::endForm() ?>

<?php if ($model->hasErrors()): ?>
	<div class="alert alert-danger alert-icon"><?= Html::encode(implode(' ', $model->getFirstErrors())) ?></div>
<?php endif; ?>

<?php if ($affiliate): ?>
	<div class="portlet box blue-hoki">
		<div class="portlet-title">
			<div class="caption"><?= Yii::t('common', 'Payable commissions') ?></div>
		</div>
		<div class="portlet-body">
			<p class="help-block">
				<?= Yii::t('common', 'A payout groups every payable commission of the affiliate in one currency (storno offsets included). Minimum payout: {minimum}.', [
					'minimum' => Yii::$app->formatter->asDecimal(AffiliateSettings::getMinimumPayout(), 2),
				]) ?>
			</p>
			<?php if ($summary): ?>
				<?php foreach ($summary as $currency => $item): ?>
					<?= Html::beginForm(['create'], 'post', ['class' => 'well well-sm']) ?>
						<?= Html::activeHiddenInput($model, 'affiliate_id', ['value' => $affiliate->uuid]) ?>
						<?= Html::activeHiddenInput($model, 'currency', ['value' => $currency]) ?>
						<p>
							<strong><?= Html::encode(Yii::$app->formatter->asDecimal($item['amount'], 2) . ' ' . $currency) ?></strong>
							<small class="text-muted">(<?= Yii::t('common', '{count, plural, one{# commission} other{# commissions}}', ['count' => $item['count']]) ?>)</small>
						</p>
						<?php if ($item['canCreate']): ?>
							<div class="form-group">
								<?= Html::activeLabel($model, 'notes', ['class' => 'control-label']) ?>
								<?= Html::activeTextarea($model, 'notes', ['class' => 'form-control', 'rows' => 3, 'id' => 'notes-' . $currency, 'value' => $model->currency === $currency ? $model->notes : '']) ?>
							</div>
							<?= Html::submitButton('<span class="fa fa-money"></span> ' . Yii::t('common', 'Create payout'), [
								'class' => 'btn btn-success',
								'data' => [
									'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]) ?>
						<?php else: ?>
							<span class="text-warning"><?= Yii::t('common', 'Below the minimum payout or not positive.') ?></span>
						<?php endif; ?>
					<?= Html::endForm() ?>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="text-info-icon fa-info-circle"><?= Yii::t('common', 'The affiliate has no payable commissions.') ?></div>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>
