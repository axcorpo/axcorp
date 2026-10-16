<?php
/* @var $this yii\web\View */

use yii\helpers\Html;
?>

<section class="section legal-page">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-10 col-xl-9">
				<p class="legal-updated"><?= Yii::t('label', 'Last Updated On') ?>: <?= Yii::$app->formatter->asDate($this->context->currentPage->updated_at, 'MMMM, yyyy') ?></p>
				<div class="rich-content legal-content" id="intro">
					<?= $this->context->currentPage->content ?>
				</div>
			</div>
		</div>
	</div>
</section>
