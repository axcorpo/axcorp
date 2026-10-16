<?php
/* @var $this yii\web\View */

use common\helpers\FontIcon;
use tws\helpers\StringHelper;
use tws\helpers\Url;
use yii\helpers\Html;
?>

<section class="section-about p-about section site-about">
	<div class="container">
		<div class="row rg-60 align-items-center">
			<div class="col-lg-6 col-md-6 col-12">
				<div class="site-about-image">
					<img src="<?= Url::to('@web/img/og-logo.png') ?>" class="img-fluid" alt="<?= Yii::$app->settings->getCategory('contact')['company'] ?>">
				</div>
			</div><!--end col-->

			<div class="col-lg-6 col-md-6 col-12">
				<div class="left-content site-about-text">
					<div class="heading-section mb-30">
						<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10">
							<i class="icon-sparkle"></i>
							<?= Yii::t('frontend', 'Cine suntem') ?>
						</p>
						<h2 class="h2 title fw-6"><?= Yii::t('frontend', 'Construim conexiuni. Dezvoltăm oportunități. Creăm viitorul digital.') ?></h2>
					</div>
					<p class="desc ff-2"><?= Yii::t('frontend', 'AXCORP SRL este o companie românească, cu sediul în Arad, orientată către dezvoltarea, administrarea și extinderea platformelor digitale, a soluțiilor tehnologice și a ecosistemelor de afaceri online.') ?></p>
					<p class="desc ff-2"><?= Yii::t('frontend', 'Ne concentrăm pe transformarea ideilor în proiecte digitale, construind infrastructuri care facilitează interacțiunea dintre persoane, companii, produse și servicii.') ?></p>
					<p class="desc ff-2"><?= Yii::t('frontend', 'Credem că tehnologia trebuie să simplifice procesele, să elimine barierele și să creeze oportunități reale de dezvoltare.') ?></p>
					<p class="desc ff-2 mb-0"><?= Yii::t('frontend', 'Prin proiectele noastre, urmărim să contribuim la digitalizarea pieței românești, oferind soluții accesibile, eficiente și adaptate permanent nevoilor utilizatorilor și mediului de afaceri.') ?></p>
				</div>
			</div><!--end col-->
		</div><!--end row-->
	</div><!--end container-->
</section><!--end section-->

<?php if ($services = \common\models\Service::findFeaturedServices(3)): ?>
<section class="section-services section site-about-services">
	<div class="container">
		<div class="heading-section style-center style-white mb-50">
			<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10">
				<i class="icon-sparkle"></i>
				<?= Yii::t('frontend', 'SECTION_SERVICES_DESCRIPTION') ?>
				<i class="icon-sparkle"></i>
			</p>
			<h2 class="h2 title fw-7"><?= Yii::t('frontend', 'SECTION_SERVICES_TITLE') ?></h2>
		</div>

		<div class="row rg-30">
			<?php foreach ($services as $service) : ?>
				<?php $serviceTranslation = $service->getTranslation(); ?>
				<div class="col-lg-4 col-md-6 col-12">
					<div class="box-services-icon h-100">
						<?php if ($service->video): ?>
							<div class="ratio ratio-16x9 mb-4 site-about-services__media">
								<iframe src="<?= $service->getVideoEmbedUrl() ?>?hl=<?= Yii::$app->language ?>&autoplay=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
							</div>
						<?php elseif (($service->image && is_file(Yii::getAlias("@uploads/service/{$service->uuid}/{$service->image}"))) || !$service->icon): ?>
							<a class="d-block mb-4 site-about-services__media" href="<?= Url::to(['/service/default/view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>">
								<img class="img-fluid" src="<?= $service->getImageUrl() ?: Url::to('@web/img/logo-symbol.png') ?>" alt="<?= $serviceTranslation->title ?>">
							</a>
						<?php else: ?>
							<div class="icon mb-4">
								<?= FontIcon::render($service->icon) ?>
							</div>
						<?php endif; ?>
						<div class="top-box">
							<a class="name-box h3 fw-6" href="<?= Url::to(['/service/default/view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>"><?= $serviceTranslation->title ?></a>
						</div>
						<?php if ($serviceTranslation->description): ?>
							<p class="desc ff-2"><?= StringHelper::truncate($serviceTranslation->description, 120) ?></p>
						<?php endif; ?>
						<a class="site-about-services__more fw-6" href="<?= Url::to(['/service/default/view', 'slug' => $serviceTranslation->slug]) ?>" title="<?= Yii::t('common', 'Read More About {0}', [Html::encode($serviceTranslation->title)]) ?>"><?= Yii::t('common', 'Read More') ?> <i class="icon-arrow-up-right"></i></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div><!--end row-->

		<div class="text-center mt-60">
			<a class="tf-btn style-big mx-auto" href="<?= Url::to(['/service/default/index']) ?>">
				<span class="text-btn"><?= Yii::t('frontend', 'See More Details') ?></span>
				<span class="icon-btn"><i class="icon-chevron-right"></i></span>
			</a>
		</div>
	</div><!--end container-->
</section><!--end section-->
<?php endif; ?>
