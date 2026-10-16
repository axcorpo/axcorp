<?php
/* @var $this yii\web\View */
/* @var $tags array */

use common\helpers\FontIcon;
use yii\helpers\Html;
use yii\helpers\Url;
?>

<?php if ($articleCategories = \common\models\ArticleCategory::findAllArticleCategories()): ?>
	<div class="sidebar-content sidebar-categories mb-50">
		<h2 class="h3 title letter-space--3 fw-6 color-title"><?= Yii::t('common', 'Categories') ?></h2>
		<ul class="list-categories-item">
			<?php foreach ($articleCategories as $articleCategory) : ?>
				<?php $articleCategoryTranslation = $articleCategory->getTranslation(); ?>
				<li class="<?= Yii::$app->request->get('category') == $articleCategoryTranslation->slug ? 'active' : '' ?>">
					<a class="item ff-2 fw-5 letter-space--2" href="<?= Url::to(['index', 'category' => $articleCategoryTranslation->slug]) ?>"><?= $articleCategoryTranslation->title ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($latestArticles = \common\models\Article::findLatestArticles(5)): ?>
	<div class="sidebar-content sidebar-recent-post mb-50">
		<h2 class="h3 title letter-space--3 fw-6 color-title"><?= Yii::t('frontend', 'Latest Articles') ?></h2>
		<div class="list-blog">
			<?php foreach ($latestArticles as $article): ?>
				<?php $articleTranslation = $article->getTranslation(); ?>
				<div class="post-list-small hover-img">
					<?php if ($article->video): ?>
						<span class="image post-list-small-video">
							<span class="ratio ratio-1x1">
								<iframe src="<?= $article->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
							</span>
						</span>
					<?php elseif ($article->image): ?>
						<a class="image" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>">
							<img loading="lazy" width="80" height="80" src="<?= $article->getImageUrl() ?>" alt="<?= $articleTranslation->title ?>">
						</a>
					<?php else: ?>
						<a class="image post-list-small-placeholder" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>">
							<?php if ($article->icon): ?>
								<?= FontIcon::render($article->icon, ['class' => 'post-media-icon']) ?>
							<?php else: ?>
								<img loading="lazy" width="80" height="80" src="<?= Url::to('@web/theme/images/blog/post-list-small-1.jpg') ?>" alt="<?= $articleTranslation->title ?>">
							<?php endif; ?>
						</a>
					<?php endif; ?>
					<span class="post-content">
						<span class="post-meta">
							<span class="day ff-2 letter-space--2 color-paragraph"><?= Yii::$app->formatter->asDate($article->created_at, 'dd MMM yyyy') ?></span>
						</span>
						<a class="name-post title text-body-2 fw-6 letter-space--3 color-title" href="<?= Url::to(['view', 'slug' => $articleTranslation->slug]) ?>"><?= $articleTranslation->title ?></a>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>

<?php if ($yearArticles = \common\models\Article::countArticlesByYear()): ?>
	<div class="sidebar-content sidebar-categories mb-50">
		<h2 class="h3 title letter-space--3 fw-6 color-title"><?= Yii::t('frontend', 'Archives') ?></h2>
		<ul class="list-categories-item">
			<?php foreach ($yearArticles as $yearArticle): ?>
				<li class="<?= Yii::$app->request->get('year') == $yearArticle['year'] ? 'active' : '' ?>">
					<a class="item ff-2 fw-5 letter-space--2" href="<?= Url::to(['index', 'year' => $yearArticle['year']]) ?>">
						<?= $yearArticle['year'] ?> <span class="color-title">(<?= $yearArticle['total'] ?>)</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if (!empty($tags)): ?>
	<div class="sidebar-content sidebar-tags mb-50">
		<h2 class="h3 title letter-space--3 fw-6 color-title"><?= Yii::t('frontend', 'Tags') ?></h2>
		<ul class="list-tags d-flex flex-wrap g-10">
			<?php foreach ($tags as $tag): ?>
				<li class="<?= Yii::$app->request->get('tag') == $tag ? 'active' : '' ?>">
					<a class="tags-item" href="<?= Url::to(['index', 'tag' => $tag]) ?>"><span><?= $tag ?></span></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
