<?php

namespace frontend\assets;

use yii\web\AssetBundle;

/**
 * The public site: the Agentra theme (web/theme, copied from /template/assets) on top of
 * Bootstrap 5, plus the application's own stylesheet and scripts.
 */
class AppAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public $basePath = '@webroot';

	/**
	 * @inheritdoc
	 */
	public $baseUrl = '@web';

	/**
	 * @inheritdoc
	 */
	public $css = [
		'theme/css/bootstrap.min.css',
		'theme/font/fonts.css',
		'theme/icons/icomoon/style.css',
		'css/materialdesignicons.min.css',
		'css/unicons-line.css',
		'theme/css/swiper-bundle.min.css',
		'theme/css/animate.min.css',
		'theme/css/odometer.min.css',
		'theme/css/magnific-popup.min.css',
		'theme/css/styles.css',
		'css/custom.css',
		// Last, so that the theme wins over the rules custom.css still carries for the old one.
		'css/theme.css',
		'css/pages/home.css',
		'css/pages/site.css',
		'css/pages/auth.css',
		'css/pages/content.css',
		// The dark variant, over everything above.
		'css/theme-dark.css',
	];

	/**
	 * @inheritdoc
	 */
	public $js = [
		'theme/js/plugin/swiper-bundle.min.js',
		'theme/js/carousel.js',
		'theme/js/plugin/lazysize.min.js',
		'theme/js/plugin/wow.min.js',
		'theme/js/plugin/infinityslide.js',
		'theme/js/plugin/plugin.min.js',
		'theme/js/gsapAnimation.js',
		'theme/js/plugin/odometer.min.js',
		'theme/js/plugin/count-down.js',
		'theme/js/main.js',
		'js/main-app.js',
	];

	/**
	 * @inheritdoc
	 */
	public $depends = [
		'frontend\assets\BootstrapAsset',
		'yii\web\YiiAsset',
		'yii\bootstrap\BootstrapPluginAsset',
		// Loaded on every page so that a phone input rendered inside an AJAX modal finds
		// intl-tel-input's stylesheet already applied. The modal injects its own <link>
		// asynchronously while the widget's init script runs at once, and an unstyled flag
		// container measures as wide as the page, which the plugin then writes into the
		// input's padding-left.
		'borales\extensions\phoneInput\PhoneInputAsset',
		'frontend\assets\NpmAsset',
		'frontend\assets\BowerAsset',
	];
}
