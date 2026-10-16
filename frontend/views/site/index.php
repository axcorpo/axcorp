<?php
/* @var $this \yii\web\View */

use common\helpers\FontIcon;
use common\helpers\HtmlSanitizer;
use tws\helpers\StringHelper;
use tws\helpers\Url;
use yii\helpers\Html;

// The homepage follows the template's "AI Agency Dark" page (template/index-dark.html)
// section by section, filled with the AXCORP presentation. Three parts come from the
// admin panel: the hero slides (the default main carousel), the services (the featured
// ones of the service module) and the page's own content.

$img = function ($path) {
	return Url::to('@web/theme/images/' . $path);
};

// Hero slides: the main carousel, or the company motto when it has no slides.
$slides = [];
if ($carousel = \common\models\Carousel::findDefaultCarousel(\common\models\Carousel::TYPE_MAIN, true)) {
	foreach ($carousel->getOrderedCarouselItems() as $carouselItem) {
		$translation = $carouselItem->getTranslation();
		if (!$translation || (empty($translation->title) && empty($translation->content))) {
			continue;
		}
		$slides[] = [
			'title' => HtmlSanitizer::websiteContent($translation->title),
			'content' => HtmlSanitizer::websiteContent($translation->content),
			'anchor' => $translation->anchor,
			'url' => $translation->anchor ? HtmlSanitizer::href($translation->url) : null,
			'target' => $carouselItem->target ?: null,
		];
	}
}
if (!$slides) {
	$slides[] = [
		'title' => Yii::t('frontend', 'Construim conexiuni. Dezvoltăm oportunități. Creăm viitorul digital.'),
		'content' => Yii::t('frontend', 'Dezvoltăm și administrăm platforme digitale, soluții tehnologice și ecosisteme de afaceri online.'),
		'anchor' => Yii::t('frontend', 'Contact Us'),
		'url' => Url::to(['/site/contact']),
		'target' => null,
	];
}

// What we do: the main directions of activity.
$directions = [
	['icon' => 'icon-cloud-based', 'title' => Yii::t('frontend', 'Platforme online și marketplace-uri'), 'text' => Yii::t('frontend', 'Dezvoltarea și administrarea platformelor online și a marketplace-urilor digitale.')],
	['icon' => 'icon-seamless', 'title' => Yii::t('frontend', 'Ecosisteme de promovare'), 'text' => Yii::t('frontend', 'Crearea unor ecosisteme de promovare și interacțiune comercială.')],
	['icon' => 'icon-ai-powered', 'title' => Yii::t('frontend', 'Publicitate și marketing digital'), 'text' => Yii::t('frontend', 'Dezvoltarea soluțiilor de publicitate și marketing digital.')],
	['icon' => 'icon-machine', 'title' => Yii::t('frontend', 'Integrări și automatizare'), 'text' => Yii::t('frontend', 'Implementarea integrărilor tehnologice și a soluțiilor de automatizare.')],
	['icon' => 'icon-union-2', 'title' => Yii::t('frontend', 'Relații comerciale'), 'text' => Yii::t('frontend', 'Facilitarea relațiilor comerciale dintre companii și clienți.')],
	['icon' => 'icon-ai-chip', 'title' => Yii::t('frontend', 'Inteligență artificială'), 'text' => Yii::t('frontend', 'Cercetarea și integrarea tehnologiilor bazate pe inteligență artificială.')],
];

// The AXCORP ecosystem.
$platforms = [
	['domain' => 'econstructii.ro', 'tag' => Yii::t('frontend', 'Construcții'), 'image' => 'ecosystem/econstructii.jpg', 'text' => Yii::t('frontend', 'Platformă dedicată sectorului construcțiilor, care conectează furnizorii de materiale, echipamente, utilaje, servicii și lucrări cu potențialii clienți și parteneri.')],
	['domain' => 'imobiliareaz.ro', 'tag' => Yii::t('frontend', 'Imobiliare'), 'image' => 'ecosystem/imobiliareaz.jpg', 'text' => Yii::t('frontend', 'Platformă de anunțuri imobiliare, destinată promovării proprietăților și facilitării interacțiunii dintre proprietari, agenții, dezvoltatori și cumpărători.')],
	['domain' => 'eautomarket.ro', 'tag' => Yii::t('frontend', 'Auto'), 'image' => 'ecosystem/eautomarket.jpg', 'text' => Yii::t('frontend', 'Platformă dedicată pieței auto, pentru promovarea și identificarea vehiculelor și a ofertelor din domeniul auto.')],
	['domain' => 'evandcumpar.ro', 'tag' => Yii::t('frontend', 'Anunțuri'), 'image' => 'ecosystem/evandcumpar.jpg', 'text' => Yii::t('frontend', 'Platformă generalistă de anunțuri, care facilitează comercializarea și promovarea unei game diversificate de produse și servicii.')],
	['domain' => 'epriceput.ro', 'tag' => Yii::t('frontend', 'Servicii'), 'image' => 'ecosystem/epriceput.jpg', 'text' => Yii::t('frontend', 'Platformă orientată către promovarea profesioniștilor, prestatorilor de servicii și firmelor specializate, facilitând identificarea acestora de către potențialii clienți.')],
];

// Vision, mission, the long run.
$process = [
	['title' => Yii::t('frontend', 'Viziunea noastră'), 'text' => Yii::t('frontend', 'Ne propunem să transformăm AXCORP într-un reper al dezvoltării ecosistemelor digitale, prin construirea unei rețele de platforme specializate, interconectate și susținute de tehnologii inteligente. Inteligența artificială, automatizarea și analiza datelor sunt direcțiile prin care îmbunătățim permanent experiența utilizatorilor.')],
	['title' => Yii::t('frontend', 'Misiunea noastră'), 'text' => Yii::t('frontend', 'Dezvoltăm soluții digitale care cresc vizibilitatea companiilor, eficientizează procesele comerciale și facilitează accesul la produse, servicii și oportunități de afaceri, cu instrumente accesibile atât IMM-urilor, cât și companiilor mari.')],
	['title' => Yii::t('frontend', 'Construim pentru viitor'), 'text' => Yii::t('frontend', 'AXCORP nu este doar un portofoliu de platforme online, ci o viziune pe termen lung: un ecosistem în care tehnologia conectează oameni, afaceri și oportunități, susținut de investiții continue și parteneriate durabile.')],
];

$services = \common\models\Service::findFeaturedServices(6);
$pageContent = $this->context->currentPage ? $this->context->currentPage->translation->content : null;


$marqueeItem = function ($text, $style = '') {
	return '<div class="infiniteSlide-slide"><div class="marquee-item"><div class="marque-text-item' . ($style ? ' ' . $style : '') . '">'
		. '<i class="icon-sparkle"></i><span class="h1 fw-7 letter-space--5 lh-72">' . Html::encode($text) . '</span>'
		. '</div></div></div>';
};
?>

<h1 class="hidden"><?= Html::encode($this->context->currentPage ? $this->context->currentPage->translation->title : 'AXCORP') ?></h1>

<!-- Hero Area -->
<section class="wg-hero-area" id="home">
	<div class="image-bg">
		<img loading="lazy" width="1920" height="550" src="<?= $img('page-title/bg-page-title-h1-dark.jpg') ?>" alt="">
	</div>
	<div class="line"></div>
	<div class="container">
		<div class="top-hero-area">
			<div class="title-animation">
				<p class="text-big-gradient">AXCORP</p>
			</div>
			<span class="dot dot-gradient dot-1"></span>
			<span class="dot dot-gradient dot-2"></span>
			<span class="dot dot-gradient dot-3"></span>
			<span class="dot dot-gradient dot-4"></span>
		</div>
	</div>
	<div class="container">
		<div class="image-absolute">
			<img loading="lazy" width="673" height="670" src="<?= $img('page-title/img-hero-arena-2.png') ?>" alt="">
		</div>
		<div class="hero-area-content">
			<div class="image-hero">
				<img loading="lazy" width="398" height="600" src="<?= $img('page-title/img-hero-arena-1.png') ?>" alt="">
			</div>

			<div class="left-content">
				<p class="sub-title">
					<i class="icon-sparkle"></i>
					<span class="ff-2 fw-5 letter-space--2 text-body-1"><?= Yii::t('frontend', 'Conectăm afaceri. Susținem creșterea.') ?></span>
				</p>
				<div dir="ltr" class="swiper tf-swiper hero-slider" data-preview="1" data-laptop="1" data-tablet="1" data-mobile-sm="1" data-mobile="1" data-space="30" data-effect="fade" data-loop="<?= count($slides) > 1 ? 'true' : 'false' ?>" data-auto="<?= count($slides) > 1 ? 'true' : 'false' ?>" data-delay="6000">
					<div class="swiper-wrapper">
						<?php foreach ($slides as $slide): ?>
							<div class="swiper-slide">
								<h2 class="title fw-6"><?= $slide['title'] ?></h2>
								<?php if ($slide['content']): ?>
									<div class="desc ff-2 fw-5 h5 letter-space-0"><?= $slide['content'] ?></div>
								<?php endif; ?>
								<?php if ($slide['url']): ?>
									<a href="<?= Html::encode($slide['url']) ?>" class="tf-btn style-big style-gradient-2"<?= $slide['target'] ? ' target="' . Html::encode($slide['target']) . '"' : '' ?>>
										<span class="text-btn"><?= Html::encode($slide['anchor']) ?></span>
										<span class="icon-btn">
											<i class="icon-chevron-right"></i>
										</span>
									</a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if (count($slides) > 1): ?>
						<div class="sw-dot-pagination tf-sw-pagination"></div>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>

	<div class="line-page line-1"></div>
</section>
<!-- ./Hero Area -->

<!-- Section Features: what we do -->
<section class="section-features h-1 section-line flat-spacing-1" id="ce-facem">
	<div class="line-page line-1"></div>

	<div class="container">
		<div class="heading-section style-center mb-56">
			<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
				<i class="icon-sparkle"></i>
				<?= Yii::t('frontend', 'Ce facem') ?>
				<i class="icon-sparkle"></i>
			</p>
			<p class="h2 title fw-7 title-animation">
				<?= Yii::t('frontend', 'Transformăm ideile') ?>
				<br>
				<?= Yii::t('frontend', 'în proiecte digitale') ?>
			</p>
		</div>
	</div>

	<div class="container w-1302">
		<div class="features-content g-10">
			<div class="features-card flex-grow-1 tf-animate-1">
				<p class="title h3 fw-6"><?= Yii::t('frontend', 'Portofoliu de platforme') ?></p>
				<div class="middel-icon">
					<div class="img-shape">
						<img src="<?= $img('icon/shape-features-card.png') ?>" alt="">
					</div>
					<div class="icon-item icon-1"><i class="icon-cloud-based"></i></div>
					<div class="icon-item icon-2"><i class="icon-sparkle-2"></i></div>
					<div class="icon-item icon-3"><i class="icon-sparkle-3"></i></div>
					<div class="icon-item icon-4"><i class="icon-grid"></i></div>
				</div>
				<p class="desc text-body-2 ff-2"><?= Yii::t('frontend', 'Dezvoltăm și administrăm un portofoliu diversificat de platforme digitale, pentru domenii economice distincte.') ?></p>
			</div>

			<div class="d-flex g-10 flex-column flex-grow-1">
				<div class="features-card-2 flex-grow-1 tf-animate-2">
					<div class="image-bg">
						<img src="<?= $img('icon/img-bg-fea-2.png') ?>" alt="">
					</div>
					<div class="card-content">
						<div class="icon features-card-2__icon">
							<i class="icon-union-1"></i>
						</div>
						<p class="title h3 fw-6"><?= Yii::t('frontend', 'Promovare și parteneriate') ?></p>
						<p class="desc ff-2 text-body-2"><?= Yii::t('frontend', 'Platforme specializate prin care companiile și persoanele fizice își promovează produsele și serviciile și găsesc noi oportunități comerciale.') ?></p>
					</div>
				</div>
				<div class="image tf-animate-3">
					<img loading="lazy" width="417" height="232" src="<?= $img('section/img-fea-h1.jpg') ?>" alt="">
				</div>
			</div>

			<div class="features-card flex-grow-1 tf-animate-4">
				<p class="title h3 fw-6"><?= Yii::t('frontend', 'Tehnologii moderne') ?></p>
				<div class="middel-icon">
					<div class="img-shape">
						<img src="<?= $img('icon/shape-features-card.png') ?>" alt="">
					</div>
					<div class="icon-item icon-1"><i class="icon-ai-head"></i></div>
					<div class="icon-item icon-2"><i class="icon-machine-learning"></i></div>
					<div class="icon-item icon-3"><i class="icon-ai-search"></i></div>
					<div class="icon-item icon-4"><i class="icon-intelligent"></i></div>
				</div>
				<p class="desc text-body-2 ff-2"><?= Yii::t('frontend', 'Integrăm tehnologii moderne și automatizăm procesele, pentru soluții interconectate, eficiente și intuitive.') ?></p>
			</div>
		</div>
	</div>
</section>
<!-- ./Section Features -->

<!-- Section About: who we are -->
<section class="section-about section-line h-1 section-style-dark" id="cine-suntem">
	<div class="img-shape">
		<img loading="lazy" width="780" height="780" src="<?= $img('section/shape-se-about-dark.png') ?>" alt="">
	</div>
	<div class="line-page line-1"></div>
	<div class="container">
		<div class="heading-section">
			<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
				<i class="icon-sparkle"></i>
				<?= Yii::t('frontend', 'Cine suntem') ?>
			</p>
			<p class="h2 title fw-7 title-animation">
				<?= Yii::t('frontend', 'AXCORP SRL este o companie românească,') ?>
				<br>
				<?= Yii::t('frontend', 'cu sediul în Arad, orientată către') ?>
				<br>
				<?= Yii::t('frontend', 'platforme și ecosisteme digitale.') ?>
			</p>
		</div>
		<div class="section-about-inner d-flex justify-content-between g-30">
			<div class="section-about-content">
				<p class="text text-title-1 lh-35 fw-6 letter-space--3 title-animation">
					<?= Yii::t('frontend', 'Credem că tehnologia trebuie să simplifice procesele, să elimine barierele și să creeze oportunități reale de dezvoltare.') ?>
				</p>
				<p class="desc ff-2 text-body-2 title-animation">
					<?= Yii::t('frontend', 'Transformăm ideile în proiecte digitale și construim infrastructuri care facilitează interacțiunea dintre persoane, companii, produse și servicii. Prin proiectele noastre contribuim la digitalizarea pieței românești, cu soluții accesibile, eficiente și adaptate permanent nevoilor utilizatorilor și mediului de afaceri.') ?>
				</p>
				<div class="title-animation">
					<a href="<?= Url::to(['/site/about-us']) ?>" class="tf-btn style-big">
						<span class="text-btn"><?= Yii::t('frontend', 'Află mai multe') ?></span>
						<span class="icon-btn">
							<i class="icon-chevron-right"></i>
						</span>
					</a>
				</div>
			</div>
			<div class="image-section tf-animate-2">
				<img loading="lazy" width="730" height="450" src="<?= $img('section/img-section-about-h1.jpg') ?>" alt="">
			</div>
			<div class="box-fact tf-animate-2">
				<div class="top-fact">
					<p class="fs-70 lh-100 letter-space--5 fw-6 ff-2">
						<span class="h1 fw-6 ff-2"><?= count($platforms) ?></span>
					</p>
				</div>
				<div class="line"></div>
				<p class="desc ff-2 text-title-1 fw-5 letter-space--3">
					<?= Yii::t('frontend', 'Platforme') ?>
					<br>
					<?= Yii::t('frontend', 'în ecosistem') ?>
				</p>
			</div>
		</div>
		<?php if ($pageContent): ?>
			<div class="rich-content home-page-content mt-60"><?= $pageContent ?></div>
		<?php endif; ?>
	</div>
</section>
<!-- ./Section About -->

<!-- Section Marquee -->
<section class="section-line section-marquee flat-spacing-1">
	<div class="line-page line-1"></div>

	<div class="container">
		<div class="infiniteSlide-app style-border-2 style-border section-dot" aria-hidden="true">
			<span class="dot dot-gradient dot-1"></span>
			<span class="dot dot-gradient dot-2"></span>
			<span class="dot dot-gradient dot-3"></span>
			<span class="dot dot-gradient dot-4"></span>
			<div class="infiniteSlide infiniteSlide-wrapper tf-marquee" data-clone="3">
				<?php foreach ($platforms as $platform): ?>
					<?= $marqueeItem($platform['domain'], 'style-2') ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
<!-- ./Section Marquee -->

<!-- Section Services -->
<section class="section-services h-1 section-line section-style-dark flat-spacing-1" id="servicii">
	<div class="line-page line-1"></div>
	<div class="image-shape image-shape-top">
		<img src="<?= $img('icon/shape-s-services-dark-h1.png') ?>" alt="">
	</div>

	<div class="container">
		<div class="heading-section style-center style-white">
			<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
				<i class="icon-sparkle"></i>
				<?= Yii::t('frontend', 'Servicii') ?>
				<i class="icon-sparkle"></i>
			</p>
			<p class="h2 title fw-7 title-animation">
				<?= Yii::t('frontend', 'Principalele noastre') ?>
				<br>
				<?= Yii::t('frontend', 'direcții de activitate') ?>
			</p>
		</div>
	</div>
	<div class="container w-1302">
		<?php
		// The featured services of the service module; until there are some, the
		// directions of activity stand in for them.
		$serviceBoxes = [];
		if ($services) {
			foreach ($services as $service) {
				$serviceTranslation = $service->getTranslation();
				$serviceBoxes[] = [
					'icon' => $service->icon ? FontIcon::render($service->icon) : '<i class="icon-sparkle"></i>',
					'title' => Html::encode($serviceTranslation->title),
					'text' => Html::encode(StringHelper::truncate(strip_tags((string) $serviceTranslation->description), 140)),
					'url' => Url::to(['/service/default/view', 'slug' => $serviceTranslation->slug]),
				];
			}
		} else {
			foreach ($directions as $direction) {
				$serviceBoxes[] = [
					'icon' => '<i class="' . $direction['icon'] . '"></i>',
					'title' => Html::encode($direction['title']),
					'text' => Html::encode($direction['text']),
					'url' => null,
				];
			}
		}
		$serviceColumns = array_chunk($serviceBoxes, (int) ceil(count($serviceBoxes) / 2));
		?>
		<div class="section-services-inner">
			<div class="image-shape-middle image-shape">
				<img src="<?= $img('icon/shape-s-services-middle.png') ?>" alt="">
				<div class="logo-shape">
					<img src="<?= Url::to('@web/img/logo-symbol-alt.png') ?>" alt="AXCORP">
				</div>
			</div>

			<?php foreach ($serviceColumns as $column): ?>
				<div class="list-services-item d-flex flex-column g-30">
					<?php foreach ($column as $box): ?>
						<div class="box-services-icon">
							<div class="top-box">
								<div class="icon">
									<?= $box['icon'] ?>
								</div>
								<?php if ($box['url']): ?>
									<a href="<?= $box['url'] ?>" class="name-box h3 fw-6"><?= $box['title'] ?></a>
								<?php else: ?>
									<span class="name-box h3 fw-6"><?= $box['title'] ?></span>
								<?php endif; ?>
							</div>
							<p class="desc ff-2"><?= $box['text'] ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ($services): ?>
			<div class="text-center mt-56">
				<a href="<?= Url::to(['/service/default/index']) ?>" class="tf-btn style-big style-gradient-2 border-radius-30">
					<span class="text-btn"><?= Yii::t('frontend', 'Toate serviciile') ?></span>
					<span class="icon-btn"><i class="icon-chevron-right"></i></span>
				</a>
			</div>
		<?php endif; ?>
	</div>
</section>
<!-- ./Section Services -->

<!-- Section Projects: the AXCORP ecosystem -->
<section class="section-project h-1 section-line section-style-dark flat-spacing-1" id="ecosistem">
	<div class="line-page line-1"></div>
	<div class="image-shape image-shape-top">
		<img src="<?= $img('icon/shape-s-services-dark-h1.png') ?>" alt="">
	</div>
	<div class="container">
		<div class="heading-section d-flex align-items-end justify-content-between rg-15 flex-wrap">
			<div class="left">
				<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
					<i class="icon-sparkle"></i>
					<?= Yii::t('frontend', 'Ecosistemul AXCORP') ?>
				</p>
				<p class="h2 title fw-7 title-animation"><?= Yii::t('frontend', 'Conectăm cererea cu oferta') ?></p>
			</div>
			<div class="right title-animation">
				<a href="<?= Url::to(['/site/contact']) ?>" class="tf-btn style-big style-gradient-2 border-radius-30">
					<span class="text-btn"><?= Yii::t('frontend', 'Devino partener') ?></span>
					<span class="icon-btn">
						<i class="icon-chevron-right"></i>
					</span>
				</a>
			</div>
		</div>
	</div>
	<div class="container w-1302">
		<div class="section-project-inner">
			<div dir="ltr" class="sw-project swiper tf-swiper" data-laptop="2" data-preview="2" data-tablet="2" data-mobile-sm="1" data-mobile="1" data-space-lg="30" data-space-md="15" data-space="15">
				<div class="swiper-wrapper">
					<?php foreach ($platforms as $platform): ?>
						<?php $platformUrl = 'https://' . $platform['domain']; ?>
						<div class="swiper-slide">
							<div class="project-item style-dark hover-img">
								<div class="top-item">
									<a href="<?= $platformUrl ?>" class="image" target="_blank" rel="noopener">
										<img loading="lazy" alt="<?= Html::encode($platform['domain']) ?>" width="600" height="350" src="<?= $img($platform['image']) ?>">
									</a>
									<div class="meta-project">
										<span class="meta-item text-caption ff-2 fw-5"><?= Html::encode($platform['tag']) ?></span>
									</div>
								</div>
								<div class="content-item">
									<div>
										<a href="<?= $platformUrl ?>" class="h3 name-project fw-7" target="_blank" rel="noopener"><?= Html::encode($platform['domain']) ?></a>
										<p class="ff-2 text-body-2 project-desc"><?= Html::encode($platform['text']) ?></p>
									</div>
									<a href="<?= $platformUrl ?>" class="tf-btn-arrow" target="_blank" rel="noopener" aria-label="<?= Html::encode($platform['domain']) ?>">
										<i class="icon-arrow-up-right"></i>
									</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="sw-dot-pagination tf-sw-pagination"></div>
			</div>
		</div>
	</div>
</section>
<!-- ./Section Projects -->

<!-- Section Process: vision, mission, future -->
<section class="section-process h-1 section-line flat-spacing-2 horizontal-accordion-wrap">
	<div class="line-page line-1"></div>
	<div class="container">
		<div class="heading-section style-center mb-56">
			<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
				<i class="icon-sparkle"></i>
				<?= Yii::t('frontend', 'Viziune și misiune') ?>
				<i class="icon-sparkle"></i>
			</p>
			<p class="h2 title fw-7 title-animation">
				<?= Yii::t('frontend', 'Un reper al dezvoltării') ?>
				<br>
				<?= Yii::t('frontend', 'ecosistemelor digitale') ?>
			</p>
		</div>
	</div>

	<div class="container w-1302">
		<div class="process-accordion horizontal-accordion" data-inactive-width="290">
			<?php foreach ($process as $i => $step): ?>
				<?php $number = sprintf('%02d', $i + 1); ?>
				<div class="process-accordion-item style-bg-2 accordion-horizontal-item<?= $i === 1 ? ' active' : '' ?>">
					<div class="naming-list">
						<div class="top-item">
							<p class="name-process">
								<span class="text-body-2 letter-space--3"><?= $number ?></span>
								<span class="text-body-2 fw-7 letter-space--3"><?= Html::encode($step['title']) ?></span>
							</p>
							<p class="text ff-2"><?= Html::encode($step['text']) ?></p>
						</div>
					</div>
					<div class="box-process">
						<div class="top-item">
							<p class="name-process">
								<span class="text-body-2 letter-space--3"><?= $number ?></span>
								<span class="text-body-2 fw-7 letter-space--3"><?= Html::encode($step['title']) ?></span>
							</p>
							<p class="text ff-2"><?= Html::encode($step['text']) ?></p>
						</div>
						<div class="list-image">
							<div class="image img-1">
								<img loading="lazy" alt="" width="370" height="230" src="<?= $img('widget/image-process-1.jpg') ?>">
							</div>
							<div class="image img-2">
								<img loading="lazy" alt="" width="230" height="230" src="<?= $img('widget/image-process-2.jpg') ?>">
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="horizontal-accordion-pagination sw-dot-pagination d-xl-none mt-30"></div>
	</div>
</section>
<!-- ./Section Process -->

<?php if ($articles = \common\models\Article::findLatestArticles(6)): ?>
	<!-- Section Blog -->
	<section class="section-blog h-1 section-line section-style-dark flat-spacing-4" id="blog">
		<div class="line-page line-1"></div>

		<div class="img-shape">
			<img src="<?= $img('section/shape-se-about-dark.png') ?>" alt="">
		</div>
		<div class="container">
			<div class="heading-section style-center mb-56">
				<p class="text-body-1 fw-5 letter-space--2 ff-2 sub-title mb-10 title-animation">
					<i class="icon-sparkle"></i>
					<?= Yii::t('frontend', 'Blog') ?>
					<i class="icon-sparkle"></i>
				</p>
				<p class="h2 title fw-7 title-animation">
					<?= Yii::t('frontend', 'Noutăți, idei și tendințe') ?>
					<br>
					<?= Yii::t('frontend', 'din lumea digitală') ?>
				</p>
			</div>
		</div>
		<div class="container w-1302">
			<div dir="ltr" class="sw-blog swiper tf-swiper" data-laptop="3" data-preview="3" data-tablet="2" data-mobile-sm="1.7" data-mobile="1" data-space-lg="10" data-space-md="10" data-space="10">
				<div class="swiper-wrapper">
					<?php foreach ($articles as $i => $article): ?>
						<?php
						$articleTranslation = $article->getTranslation();
						$articleUrl = Url::to(['/article/default/view', 'slug' => $articleTranslation->slug]);
						?>
						<div class="swiper-slide">
							<div class="post-grid no-desc style-dark hover-img">
								<a href="<?= $articleUrl ?>" class="image">
									<img loading="lazy" alt="<?= Html::encode($articleTranslation->title) ?>" width="417" height="430" src="<?= $article->getImageUrl() ?: $img('blog/p-grid-no-desc-' . ($i % 3 + 1) . '.jpg') ?>">
								</a>
								<div class="post-content">
									<a href="<?= $articleUrl ?>" class="name-post h4 fw-7"><?= Html::encode($articleTranslation->title) ?></a>
									<div class="meta-post">
										<?php if ($article->creator): ?>
											<span class="meta-item ff-2"><?= Html::encode($article->creator->shortName) ?></span>
										<?php endif; ?>
										<span class="meta-item ff-2"><?= Yii::$app->formatter->asDate($article->created_at, 'dd MMMM yyyy') ?></span>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="text-center mt-56">
				<a href="<?= Url::to(['/article/default/index']) ?>" class="tf-btn style-big style-gradient-2 border-radius-30">
					<span class="text-btn"><?= Yii::t('frontend', 'Toate articolele') ?></span>
					<span class="icon-btn"><i class="icon-chevron-right"></i></span>
				</a>
			</div>
		</div>
	</section>
	<!-- ./Section Blog -->
<?php endif; ?>
