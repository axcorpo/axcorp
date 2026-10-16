<?php
/* @var $this yii\web\View */
/* @var $model \frontend\modules\account\models\PaymentResult */

use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Yii::t('frontend', 'Payment Result');
$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="section section-lg">
	<div class="container-fluid">
		<?php if ($model->hasErrors()): ?>
			<header class="section-header text-center">
				<h1 class="section-heading color-danger font-md"><?= Yii::t('frontend', 'Payment was unsuccessful.') ?></h1>
			</header>
			<div class="gap-b-md">
				<p class="color-grey text-center"><?= Yii::t('frontend', 'What happened?') ?></p>
				<div class="row">
					<div class="col-md-offset-1 col-md-10 col-lg-offset-2 col-lg-8">
						<?= Html::errorSummary($model, [
							'header' => false,
							'class' => 'error-summary alert alert-danger alert-icon',
						]) ?>
					</div>
				</div>
			</div>
			<div class="text-center">
				<p class="color-grey gap-b-md"><?= Yii::t('frontend', 'What can you do now?') ?></p>
				<ul class="list-inline">
					<li class="inline-block-sm">
						<a class="btn btn-default btn-outline btn-slide-right" href="<?= Url::to(['/account/payment/package', 'id' => $model->getSubscription() ? Yii::$app->security->maskToken((string) $model->getSubscription()->package_id) : null]) ?>"><?= Yii::t('frontend', 'Try Again') ?></a>
					</li>
					<li><?= mb_strtolower(Yii::t('common', 'Or')) ?></li>
					<li class="inline-block-sm">
						<a class="btn btn-default btn-outline btn-slide-right" href="<?= Url::to(['/site/contact']) ?>"><?= Yii::t('frontend', 'Contact Us') ?></a>
					</li>
				</ul>
			</div>
		<?php else: ?>
			<header class="section-header text-center">
				<h1 class="section-heading color-success font-md"><?= Yii::t('frontend', 'Payment was successful.') ?></h1>
				<p class="section-subheading color-grey"><?= Yii::t('frontend', 'What can you do now?') ?></p>
			</header>
			<div class="text-center">
				<ul class="list-inline">
					<li class="inline-block-sm">
						<a class="btn btn-default btn-outline btn-slide-right" href="<?= Url::to(['/account/invoice/index']) ?>"><?= Yii::t('frontend', 'View your invoices') ?></a>
					</li>
					<li><?= mb_strtolower(Yii::t('common', 'Or')) ?></li>
					<li class="inline-block-sm">
						<a class="btn btn-default btn-outline btn-slide-right" href="<?= Url::to(['/account/workspace/index']) ?>"><?= Yii::t('frontend', 'Manage Your Workspaces') ?></a>
					</li>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
