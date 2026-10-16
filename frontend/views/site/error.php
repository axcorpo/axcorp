<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Html::encode($message);
?>

<section class="section-404 flat-spacing-1">
	<div class="container">
		<div class="wg-404">
			<div class="image mb-50">
				<img loading="lazy" src="<?= Url::to('@web/theme/images/widget/img-404.png') ?>" width="707" height="550" alt="<?= Html::encode(Yii::$app->name) ?>">
			</div>
			<div class="wg-content text-center">
				<p class="h2 title fw-6 color-title mb-20"><?= Yii::t('frontend', 'Something is wrong') ?></p>
				<p class="desc ff-2 color-paragraph mb-30"><?= Yii::t('frontend', 'The page you are looking for was moved, removed, renamed or might never existed.') ?></p>
				<a href="<?= Url::to(['/site/index']) ?>" class="tf-btn style-big">
					<span class="text-btn"><?= Yii::t('frontend', 'Go to Home Page') ?></span>
					<span class="icon-btn">
						<i class="icon-chevron-right"></i>
					</span>
				</a>
			</div>
		</div>
	</div>
</section>
