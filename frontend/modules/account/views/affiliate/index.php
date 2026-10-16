<?php
/* @var $this yii\web\View */
/* @var $affiliate common\models\Affiliate */
/* @var $dashboard frontend\modules\account\models\AffiliateDashboard */

use common\models\AffiliateCommission;
use tws\helpers\Url;
use yii\helpers\Html;

$this->title = $this->title ?: Yii::t('frontend', 'Affiliate Dashboard');
$this->params['breadcrumbs'][] = Html::encode($this->title);
$formatter = Yii::$app->formatter;
$revenue = $dashboard->getRevenueTotals();
$commissions = $dashboard->getCommissionTotals();
$company = $affiliate->company;

// Copy button of the referral link; the input stays selectable as a fallback.
$this->registerJs(<<<JS
$(document).on('click', '[data-copy-target]', function () {
	var button = $(this), input = document.getElementById(button.data('copy-target'));
	input.select();
	var done = function () {
		button.text(button.data('copied-label'));
		setTimeout(function () { button.text(button.data('copy-label')); }, 2000);
	};
	if (navigator.clipboard && window.isSecureContext) {
		navigator.clipboard.writeText(input.value).then(done);
	} else {
		document.execCommand('copy');
		done();
	}
});
JS);
?>

<div class="tab-pane fade shadow show active rounded p-4" id="affiliate-dashboard" role="tabpanel">
	<?php if ($this->context->currentPage && ($content = $this->context->currentPage->content)): ?>
		<header class="section-header mb-4">
			<?= $content ?>
		</header>
	<?php endif; ?>

	<div class="row">
		<div class="col-md-6 mb-3">
			<div class="card border-0 shadow-sm rounded p-3 h-100">
				<span class="text-muted"><?= Yii::t('frontend', 'Signups') ?></span>
				<h4 class="mb-0"><?= $formatter->asInteger($dashboard->getSignupCount()) ?></h4>
			</div>
		</div>
		<div class="col-md-6 mb-3">
			<div class="card border-0 shadow-sm rounded p-3 h-100">
				<span class="text-muted"><?= Yii::t('frontend', 'Active subscriptions') ?></span>
				<h4 class="mb-0"><?= $formatter->asInteger($dashboard->getActiveSubscriptionCount()) ?></h4>
			</div>
		</div>
	</div>

	<h5 class="mt-3 mb-3"><?= Yii::t('frontend', 'Revenue and commissions') ?></h5>
	<?php if ($currencies = $dashboard->getCurrencies()): ?>
		<div class="table-responsive">
			<table class="table table-bordered table-hover mb-0">
				<thead>
					<tr>
						<th><?= Yii::t('label', 'Currency') ?></th>
						<th class="text-end"><?= Yii::t('frontend', 'Revenue generated (net)') ?></th>
						<th class="text-end"><?= Yii::t('frontend', 'Commissions pending') ?></th>
						<th class="text-end"><?= Yii::t('frontend', 'Commissions payable') ?></th>
						<th class="text-end"><?= Yii::t('frontend', 'Commissions paid') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($currencies as $currency): ?>
						<tr>
							<td><?= Html::encode($currency) ?></td>
							<td class="text-end"><?= $formatter->asCurrency($revenue[$currency] ?? 0, $currency) ?></td>
							<?php foreach ([AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE, AffiliateCommission::STATUS_PAID] as $status): ?>
								<td class="text-end"><?= $formatter->asCurrency($commissions[$currency][$status] ?? 0, $currency) ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="text-muted small mt-2 mb-0"><?= Yii::t('frontend', 'Pending commissions become payable after the holding period, so that refunds can still be deducted.') ?></p>
	<?php else: ?>
		<div class="alert alert-info mb-0" role="alert"><?= Yii::t('frontend', 'There are no commissions yet.') ?></div>
	<?php endif; ?>

	<?= $this->render('_charts', ['dashboard' => $dashboard]) ?>

	<h5 class="mt-4 mb-3"><?= Yii::t('frontend', 'Your referral link') ?></h5>
	<p class="mb-2">
		<?= Yii::t('frontend', 'Your affiliate code') ?>: <code><?= Html::encode($affiliate->code) ?></code>
		&middot;
		<?= Yii::t('frontend', 'Commission') ?>: <strong><?= $formatter->asDecimal($affiliate->commission_percentage, 2) ?>%</strong>
		<?php if ($affiliate->commission_months): ?>
			<?= Yii::t('frontend', 'for the first {n, plural, =1{month} other{# months}} of each subscriber', ['n' => (int) $affiliate->commission_months]) ?>
		<?php endif; ?>
	</p>
	<div class="mb-3">
		<label class="visually-hidden" for="affiliate-link"><?= Yii::t('frontend', 'Your referral link') ?></label>
		<div class="input-group">
			<input type="text" class="form-control" id="affiliate-link" value="<?= Html::encode($dashboard->getReferralUrl()) ?>" readonly>
			<?= Html::button(Yii::t('frontend', 'Copy'), [
				'class' => 'btn btn-outline-primary',
				'data' => [
					'copy-target' => 'affiliate-link',
					'copy-label' => Yii::t('frontend', 'Copy'),
					'copied-label' => Yii::t('frontend', 'Copied'),
				],
			]) ?>
		</div>
		<div class="form-text"><?= Yii::t('frontend', 'Add ?aff={code} to the address of any page of our site to make it a referral link too.', ['code' => Html::encode($affiliate->code)]) ?></div>
	</div>

	<h5 class="mt-4 mb-3"><?= Yii::t('frontend', 'Billing data') ?></h5>
	<?php if ($company): ?>
		<p class="mb-0">
			<?= Yii::t('frontend', 'Payouts are made to {company}, IBAN {iban}.', [
				'company' => Html::encode($company->name),
				'iban' => $company->bank_account ? Html::encode($company->bank_account) : '&mdash;',
			]) ?>
			<?= Html::a(Yii::t('frontend', 'Update your company'), Url::to(['/account/company/index'])) ?>
		</p>
	<?php else: ?>
		<div class="alert alert-warning mb-0" role="alert">
			<?= Yii::t('frontend', 'Add your company, with its IBAN, so that we can pay your commissions.') ?>
			<?= Html::a(Yii::t('frontend', 'Add your company'), Url::to(['/account/company/index']), ['class' => 'alert-link']) ?>
		</div>
	<?php endif; ?>
</div>
