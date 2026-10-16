<?php
/* @var $this \yii\web\View */

use frontend\assets\AppAsset;
use yii\helpers\Html;
use tws\helpers\Url;

$appLanguages = \common\models\Language::findAllLanguages();
$appLanguagesCount = count($appLanguages);
$currentAppLanguage = $appLanguages[Yii::$app->language];

AppAsset::register($this);

$headerMenu = \common\models\Menu::findDefaultHeaderMenu() ?: \common\models\Menu::findDefaultTopBarMenu();
?>
<!-- Header -->
<header class="header header-fixed style-2">
	<div class="container w-975">
		<div class="header-content">
			<a href="<?= Url::to(['/site/index']) ?>" class="logo">
				<img src="<?= Url::to('@web/img/logo-alt.png') . '?v=' . @filemtime(Yii::getAlias('@webroot/img/logo-alt.png')) ?>" alt="<?= Yii::$app->name ?>" class="site-logo">
			</a>
			<div class="header-center d-none d-xl-block">
				<nav class="box-navigation">
					<?= $this->render('_nav', ['menu' => $headerMenu, 'class' => 'box-nav-menu']) ?>
				</nav>
			</div>
			<div class="header-right d-flex align-items-center g-20">
				<?php if ($appLanguagesCount > 1): ?>
					<div class="dropdown header-dropdown">
						<button type="button" class="header-icon-btn dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= Html::encode($currentAppLanguage->name) ?>">
							<img src="<?= Url::to("@web/img/flags/{$currentAppLanguage->country}.png") ?>" alt="<?= Yii::$app->language ?>" style="width: 18px; height: auto;">
						</button>
						<div class="dropdown-menu dropdown-menu-end">
							<?php foreach ($appLanguages as $language): ?>
								<?php if ($language->language_id != Yii::$app->language): ?>
									<?php
									$currentRoute = Yii::$app->request->getQueryParams();
									$currentRoute['language'] = $language->language_id;
									array_unshift($currentRoute, substr(Yii::$app->requestedRoute, 0, 1) == '/' ? Yii::$app->requestedRoute : '/' . Yii::$app->requestedRoute);
									?>
									<a class="dropdown-item" href="<?= Url::to($currentRoute) ?>">
										<?= Html::img(Url::to("@web/img/flags/{$language->country}.png"), ['alt' => $language->name, 'style' => 'width: 18px; margin-top: -3px; margin-right: 6px;']) ?><?= mb_strtoupper($language->language) ?>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
				<div class="d-sm-block d-none">
					<a href="<?= Url::to(['/site/contact']) ?>" class="tf-btn style-gradient-2">
						<span class="text-btn"><?= Yii::t('frontend', 'Contact Us') ?></span>
						<span class="icon-btn">
							<i class="icon-chevron-right"></i>
						</span>
					</a>
				</div>
				<div class="d-xl-none">
					<a href="#mobileMenu" data-bs-toggle="offcanvas" class="mobile-button">
						<span></span>
						<span></span>
						<span></span>
					</a>
				</div>
			</div>
		</div>
	</div>
</header>
<!-- ./Header -->
