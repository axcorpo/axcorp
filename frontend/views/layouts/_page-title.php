<?php
/* @var $this \yii\web\View */
/* @var $title string */

use tws\helpers\Url;
use yii\helpers\Html;
use yii\widgets\Breadcrumbs;
?>
<!-- Page Title -->
<section class="page-title">
	<div class="image-bg">
		<img loading="lazy" width="1920" height="550" src="<?= Url::to('@web/theme/images/page-title/page-title-2.jpg') ?>" alt="">
	</div>
	<div class="container">
		<div class="page-title-content">
			<h1 class="title-page"><?= Html::encode($title) ?></h1>
			<?= Breadcrumbs::widget([
				'tag' => 'div',
				'options' => [
					'class' => 'breadkcum',
				],
				'homeLink' => [
					'label' => Yii::t('yii', 'Home'),
					'url' => Yii::$app->homeUrl,
					'class' => 'text-title-1 fw-6',
				],
				'itemTemplate' => "<span class=\"text-title-1 fw-6\">{link}</span><span class=\"text-title-1 fw-6\">/</span>\n",
				'activeItemTemplate' => "<span class=\"text-title-1 fw-6 text-in-page\">{link}</span>\n",
				'encodeLabels' => false,
				'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
			]) ?>
		</div>
	</div>
</section>
<!-- ./Page Title -->
