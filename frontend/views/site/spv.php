<?php
/* @var $this yii\web\View */
/* @var $model common\models\ActivateAccountForm */

use tws\helpers\Url;
use yii\helpers\Html;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<section class="section auth-section">
	<div class="container">
		<?php if (Yii::$app->user->identity->authAssignment->item_name === 'superAdmin' || !empty($workspace)): ?>
			<?php if (empty($integration)): ?>
				<?php if ($content = $this->context->currentPage->content): ?>
					<div class="auth-intro rich-content text-center">
						<?= $content ?>
					</div>
				<?php endif; ?>

				<div class="row g-4 justify-content-center">
					<div class="col-lg-6">
						<div class="auth-card auth-card--fluid h-100">
							<div class="form-content">
								<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('frontend', 'I have access with a digital certificate') ?></p>
								<p class="auth-text">
									<?= Yii::t('frontend', 'Choose this option if you have a digital certificate and access to S.P.V. for this company.') ?>
								</p>
								<div class="mt-4">
									<?= Html::a('<span class="text-btn">' . $anchor . '</span><span class="icon-btn"><i class="icon-chevron-right"></i></span>', $link, ['class' => 'tf-btn style-big style-gradient-2 style-w-full w-100']) ?>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-6">
						<div class="auth-card auth-card--fluid h-100">
							<div class="form-content">
								<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('frontend', 'Obtain access through the accountant') ?></p>
								<p class="auth-text">
									<?= Yii::t('frontend', 'Copy and send the link below to your accountant so they can authorize your {item} account in S.P.V.', ['item' => Yii::$app->name]) ?>
								</p>
								<div class="position-relative mt-4">
									<div class="input-group spv-copy">
										<span id="copyButton" class="input-group-text" role="button" title="<?= Yii::t('common', 'Click To Copy') ?>">
											<i class="uil uil-copy" aria-hidden="true"></i>
										</span>
										<input type="text" id="copyTarget" class="form-control" value="<?= $link ?>" readonly>
										<span class="copied"><?= Yii::t('common', 'Copied') ?></span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php else: ?>
				<div class="auth-card text-center">
					<div class="form-content">
						<i class="uil uil-check-circle auth-success-icon"></i>
						<p class="title-form text-title-1 fw-7 letter-space--3"><?= Yii::t('common', 'Success!') ?></p>
						<p class="auth-text"><?= Yii::t('common', 'The authorization of account access for {item} in S.P.V. is completed.', ['item' => Yii::$app->name]) ?></p>
						<div class="mt-4">
							<a class="tf-btn style-big style-gradient-2 style-w-full w-100" href="<?= Url::to(['/site/index']) ?>"><span class="text-btn"><?= Yii::t('frontend', 'Go to Home Page') ?></span><span class="icon-btn"><i class="icon-chevron-right"></i></span></a>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>

<?php
$this->registerJs('
	(function() {
		"use strict";
		function copyToClipboard(elem) {
			var target = elem;
			// select the content
			var currentFocus = document.activeElement;
			target.focus();
			target.setSelectionRange(0, target.value.length);
			// copy the selection
			var succeed;
			try {
				succeed = document.execCommand("copy");
			} catch (e) {
				console.warn(e);
				succeed = false;
			}
			// Restore original focus
			if (currentFocus && typeof currentFocus.focus === "function") {
				currentFocus.focus();
			}
		if (succeed) {
			$(".copied").animate({ top: -30, opacity: 1 }, 700, function() {
				$(this).css({ top: -30, opacity: 0 });
			});
		}
			return succeed;
		}
		$("#copyButton, #copyTarget").on("click", function() {
			copyToClipboard(document.getElementById("copyTarget"));
		});
	})();
');
?>
