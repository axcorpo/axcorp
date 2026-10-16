<?php
/* @var $this \yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

use common\helpers\FontIcon;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

if (Yii::$app->request->get('category')) {
	$this->params['breadcrumbs'][] = [
		'label' => \common\models\Page::findPageByRoute(['/service/default/index'])->translation->title,
		'url' => ['index'],
	];
}
$this->params['breadcrumbs'][] = Html::encode($this->title);
$tags = [];
?>

<section class="section-services p-service content-page flat-spacing-1">
	<div class="container">
		<?php if ($content = $this->context->currentPage->content): ?>
			<header class="content-page-intro rich-content mb-50">
				<?= $content ?>
			</header>
		<?php endif; ?>
		<?php $i = 0;  if ($services = $dataProvider->getModels()): ?>
			<?php $servicesCount = count($services); ?>
			<div class="row rg-30 services-row">
				<?php foreach ($services as $service) : ?>
					<?php
					/** @var common\models\Service $service */
					$serviceTranslation = $service->getTranslation();
					$tags = array_merge($tags, explode(',', $serviceTranslation->keywords));
					?>
					<div class="col-lg-4 col-sm-6">
						<article class="services-item-2 service-card">
							<?php if ($service->video): ?>
								<div class="service-card-media">
									<div class="ratio ratio-16x9">
										<iframe src="<?= $service->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
									</div>
								</div>
							<?php elseif (($service->image && is_file(Yii::getAlias("@uploads/service/{$service->uuid}/{$service->image}"))) || !$service->icon): ?>
								<a class="service-card-media" href="<?= Url::to(['view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>">
									<img loading="lazy" src="<?= $service->getImageUrl() ?: Url::to('@web/theme/images/section/img-s-service-details.jpg') ?>" alt="<?= $serviceTranslation->title ?>">
								</a>
							<?php else: ?>
								<a class="icon" href="<?= Url::to(['view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>">
									<?= FontIcon::render($service->icon, ['class' => 'service-card-icon']) ?>
								</a>
							<?php endif; ?>
							<h2 class="service-card-title">
								<a class="name-item h3 fw-6" href="<?= Url::to(['view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>"><?= $serviceTranslation->title ?></a>
							</h2>
							<div class="line"></div>
							<?php if ($serviceTranslation->description): ?>
								<p class="desc ff-2 equalize"><?= StringHelper::truncate($serviceTranslation->description, 120) ?></p>
							<?php endif; ?>
							<a class="tf-btn style-color-bg-1 style-w-full border-radius-30" href="<?= Url::to(['view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>">
								<span class="text-btn"><?= Yii::t('common', 'Read More') ?></span>
								<span class="icon-btn">
									<i class="icon-chevron-right"></i>
								</span>
							</a>
						</article>
					</div>
					<?php $i++; ?>
					<?php if ($i % 3 == 0 && $i != $servicesCount): ?>
						</div><div class="row rg-30 services-row">
					<?php endif; ?>
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
		<?php endif; ?>
	</div>
</section>

<div class="container service-categories-strip">
	<?= $this->render('_sidebar-content', [
		'tags' => array_filter($tags),
	]) ?>
</div>

<?php
$this->registerJs('
	var maxHeight = 0;
	$(".equalize").each(function(){
		if ($(this).height() > maxHeight) { maxHeight = $(this).height(); }
	});
	$(".equalize").height(maxHeight);
');
?>
