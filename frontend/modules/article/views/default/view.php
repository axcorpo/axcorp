<?php
/* @var $this \yii\web\View */
/* @var $model common\models\Article */
/* @var $modelTranslation common\models\ArticleTranslation */
/* @var $tags array */

use common\helpers\FontIcon;
use tws\widgets\socialshare\SocialShare;
use yii\helpers\Html;
use yii\helpers\Url;

$this->params['breadcrumbs'][] = [
	'label' => \common\models\Page::findPageByRoute(['/article/default/index'])->translation->title,
	'url' => ['index'],
];
$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<section class="section-blog-details content-page flat-spacing-1">
	<div class="container">
		<div class="row">
			<div class="col-xl-8 content">
				<article class="blog-details-content mb-40">
					<?php if ($model->video): ?>
						<div class="image-details post-media-video mb-40">
							<div class="ratio ratio-16x9">
								<iframe src="<?= $model->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
							</div>
						</div>
					<?php elseif ($model->image && is_file(Yii::getAlias("@uploads/article/{$model->uuid}/{$model->image}"))): ?>
						<div class="image-details mb-40">
							<img src="<?= $model->getImageUrl() ?>" alt="<?= $modelTranslation->title ?>">
						</div>
					<?php elseif ($model->icon): ?>
						<div class="image-details post-media-placeholder mb-40">
							<?= FontIcon::render($model->icon, ['class' => 'post-media-icon']) ?>
						</div>
					<?php endif; ?>
					<div class="meta-post mb-20">
						<span class="meta-item ff-2 item-color-blue"><?= Yii::$app->formatter->asDate($model->created_at, 'dd MMMM yyyy') ?></span>
						<?php if ($model->creator): ?>
							<span class="meta-item ff-2"><?= Yii::t('label', 'Created By') ?>&nbsp;<span class="color-title"><?= $model->creator->shortName ?></span></span>
						<?php endif; ?>
						<span class="meta-item ff-2" data-toggle="tooltip" title="<?= Yii::t('label', 'Views') ?>"><i class="uil uil-eye"></i>&nbsp;<?= $model->views ?></span>
					</div>
					<h2 class="title text-title-3 fw-6 letter-space--3 color-title mb-30"><?= $modelTranslation->title ?></h2>
					<div class="rich-content">
						<?= $modelTranslation->content ?>
					</div>
				</article>

				<div class="tag-share mb-40">
					<?php if (!empty($tags)): ?>
						<div class="tags">
							<p class="h3 title letter-space--3 fw-6 color-title mb-0"><?= Yii::t('frontend', 'Tags') ?></p>
							<ul class="list-tags d-flex flex-wrap g-10">
								<?php foreach ($tags as $tag): ?>
									<li>
										<a class="tags-item" href="<?= Url::to(['index', 'tag' => $tag]) ?>"><span><?= $tag ?></span></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
					<div class="share social-share-section">
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

				<nav class="post-nav" aria-label="<?= Yii::t('common', 'Articles') ?>">
					<?php if ($prevModel = $model->getPrevArticle()): ?>
						<?php $prevModelTranslation = $prevModel->getTranslation(); ?>
						<a class="post-nav-item previous" href="<?= Url::to(['view', 'slug' => $prevModelTranslation->slug]) ?>" title="<?= Html::encode($prevModelTranslation->title) ?>">
							<span class="post-nav-label ff-2 color-paragraph"><i class="icon-chevron-right post-nav-icon-prev" aria-hidden="true"></i> <?= Yii::t('frontend', 'Previous') ?></span>
							<span class="post-nav-title fw-6 letter-space--3 color-title"><?= Html::encode($prevModelTranslation->title) ?></span>
						</a>
					<?php endif; ?>
					<?php if ($nextModel = $model->getNextArticle()): ?>
						<?php $nextModelTranslation = $nextModel->getTranslation(); ?>
						<a class="post-nav-item next" href="<?= Url::to(['view', 'slug' => $nextModelTranslation->slug]) ?>" title="<?= Html::encode($nextModelTranslation->title) ?>">
							<span class="post-nav-label ff-2 color-paragraph"><?= Yii::t('frontend', 'Next') ?> <i class="icon-chevron-right" aria-hidden="true"></i></span>
							<span class="post-nav-title fw-6 letter-space--3 color-title"><?= Html::encode($nextModelTranslation->title) ?></span>
						</a>
					<?php endif; ?>
				</nav>
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
