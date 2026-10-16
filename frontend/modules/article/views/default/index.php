<?php
/* @var $this \yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $tags array */

use common\helpers\FontIcon;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

if (Yii::$app->request->get('category') || Yii::$app->request->get('tag')) {
	$this->params['breadcrumbs'][] = [
		'label' => \common\models\Page::findPageByRoute(['/article/default/index'])->translation->title,
		'url' => ['index'],
	];
}
$this->params['breadcrumbs'][] = Html::encode($this->title);
$tags = [];
?>

<section class="section-blog-standard content-page flat-spacing-1">
	<div class="container">
		<?php if ($content = $this->context->currentPage->content): ?>
			<header class="content-page-intro rich-content mb-50">
				<?= $content ?>
			</header>
		<?php endif; ?>
		<div class="row">
			<div class="col-xl-8 content">
				<?php if ($articles = $dataProvider->getModels()): ?>
					<div class="list-posts">
						<?php foreach ($articles as $article) : ?>
							<?php
							/** @var common\models\Article $article */
							$articleTranslation = $article->getTranslation();
							$tags = array_merge($tags, explode(',', $articleTranslation->keywords));
							?>
							<article class="post-grid hover-img">
								<?php if ($article->video): ?>
									<div class="image post-media-video">
										<div class="ratio ratio-16x9">
											<iframe src="<?= $article->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
										</div>
									</div>
								<?php elseif ($article->image && is_file(Yii::getAlias("@uploads/article/{$article->uuid}/{$article->image}"))): ?>
									<a class="image" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($articleTranslation->title)]) ?>">
										<img loading="lazy" src="<?= $article->getImageUrl() ?>" alt="<?= $articleTranslation->title ?>">
									</a>
								<?php else: ?>
									<a class="image post-media-placeholder" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($articleTranslation->title)]) ?>">
										<?php if ($article->icon): ?>
											<?= FontIcon::render($article->icon, ['class' => 'post-media-icon']) ?>
										<?php else: ?>
											<img loading="lazy" src="<?= Url::to('@web/theme/images/blog/post-grid-1.jpg') ?>" alt="<?= $articleTranslation->title ?>">
										<?php endif; ?>
									</a>
								<?php endif; ?>
								<div class="post-content">
									<div class="meta-post">
										<span class="meta-item ff-2 item-color-blue"><?= Yii::$app->formatter->asDate($article->created_at, 'dd MMMM yyyy') ?></span>
										<?php if ($article->creator): ?>
											<span class="meta-item ff-2"><?= Yii::t('label', 'Created By') ?>&nbsp;<span class="color-title"><?= $article->creator->shortName ?></span></span>
										<?php endif; ?>
										<span class="meta-item ff-2" data-toggle="tooltip" title="<?= Yii::t('label', 'Views') ?>"><i class="uil uil-eye"></i>&nbsp;<?= $article->views ?></span>
									</div>
									<h2 class="post-title">
										<a class="text-title-2 fw-6 letter-space--3 name-post" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($articleTranslation->title)]) ?>"><?= $articleTranslation->title ?></a>
									</h2>
									<?php if ($articleTranslation->description): ?>
										<p class="desc ff-2 color-paragraph"><?= StringHelper::truncate($articleTranslation->description, 180) ?></p>
									<?php endif; ?>
									<a class="tf-btn mt-4" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($articleTranslation->title)]) ?>">
										<span class="text-btn"><?= Yii::t('common', 'Read More') ?></span>
										<span class="icon-btn">
											<i class="icon-chevron-right"></i>
										</span>
									</a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<div class="wg-pagination">
						<?= LinkPager::widget([
							'pagination' => $dataProvider->pagination,
							'maxButtonCount' => 5,
							'registerLinkTags' => true,
							'prevPageLabel' => '&lsaquo;',
							'nextPageLabel' => '&rsaquo;',
							'firstPageLabel' => '&laquo;',
							'lastPageLabel' => '&raquo;',
							'options' => ['class' => 'pagination'],
							'linkContainerOptions' => ['class' => 'page-item'],
							'linkOptions' => ['class' => 'page-link'],
							'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
						]) ?>
					</div>
				<?php else: ?>
					<div class="alert alert-info" role="alert"><?= Yii::t('common', 'No records found.') ?></div>
				<?php endif; ?>
			</div>
			<aside class="col-xl-4 sidebar">
				<div class="tf-sidebar">
					<?= $this->render('_sidebar-content', [
						'tags' => array_filter($tags),
					]) ?>
				</div>
			</aside>
		</div>
	</div>
</section>
