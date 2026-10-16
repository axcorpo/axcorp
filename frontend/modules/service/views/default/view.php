<?php
/* @var $this \yii\web\View */
/* @var $model common\models\Service */
/* @var $modelTranslation common\models\ServiceTranslation */

use common\helpers\FontIcon;
use tws\widgets\socialshare\SocialShare;
use yii\helpers\Html;
use yii\helpers\Url;

$this->params['breadcrumbs'][] = [
	'label' => \common\models\Page::findPageByRoute(['/service/default/index'])->translation->title,
	'url' => ['index'],
];
$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<section class="section-service-details content-page flat-spacing-1">
	<div class="container">
		<div class="row">
			<div class="col-xl-8 content">
				<?php if ($model->image && is_file(Yii::getAlias("@uploads/service/{$model->uuid}/{$model->image}")) && !$model->video): ?>
					<div class="image-details mb-50">
						<img src="<?= $model->getImageUrl() ?>" alt="<?= Html::encode($modelTranslation->title) ?>">
					</div>
				<?php endif; ?>
				<div class="detail-content mb-50">
					<?php if ($model->video): ?>
						<div class="image-details post-media-video mb-50">
							<div class="ratio ratio-16x9">
								<iframe src="<?= $model->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
							</div>
						</div>
					<?php elseif ($model->icon && !$model->image): ?>
						<div class="service-detail-icon mb-40">
							<?= FontIcon::render($model->icon, ['class' => 'service-card-icon']) ?>
						</div>
					<?php endif; ?>
					<div class="rich-content">
						<?= $modelTranslation->content ?>
					</div>
				</div>
				<div class="tag-share social-share-section">
					<div class="share">
						<p class="h3 title letter-space--3 fw-6 color-title mb-0"><?= Yii::t('common', 'Share') ?></p>
						<?= SocialShare::widget([
							'options' => [
								'itemOptions' => [
									'buttonOptions' => [
										'class' => 'item-social',
									],
								],
							],
						]) ?>
					</div>
					<div class="fb-like"
						data-href="<?= Url::canonical() ?>"
						data-layout="button_count"
						data-action="like"
						data-size="large"
						data-show-faces="true"
						data-share="false">
					</div>
				</div>
			</div>
			<aside class="col-xl-4 sidebar">
				<div class="tf-sidebar">
					<?= $this->render('_sidebar-content', [
						'tags' => $tags,
					]) ?>
				</div>
			</aside>
		</div>
	</div>
</section>
