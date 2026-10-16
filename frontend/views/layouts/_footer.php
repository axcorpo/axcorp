<?php
/* @var $this \yii\web\View */

use frontend\models\AcceptCookiesForm;
use frontend\models\NewsletterForm;
use yii\helpers\Html;
use tws\helpers\Url;
use yii\widgets\ActiveForm;

$newsletterModel = new NewsletterForm();
if (Yii::$app->user && !Yii::$app->user->isGuest) {
	$newsletterModel->email = Yii::$app->user->identity->email;
	$newsletterModel->phone = Yii::$app->user->identity->phone;
}

$contact = Yii::$app->settings->getCategory('contact');
$socialNetwork = Yii::$app->settings->getCategory('socialNetwork');
$phone = $contact['mobilePhone'] ?: $contact['fixedPhone'];
$address = implode(', ', array_filter([
	$contact['streetName'],
	$contact['streetNumber'],
	$contact['locality'],
	$contact['zipCode'],
	$contact['county'],
	$contact['country'] ? \common\models\Country::findAllCountries()[$contact['country']]->translation->name : null,
]));

// The links column: the footer menu, or the header one (with Home first, as in the header)
// while no footer menu is set up.
$footerItems = [];
$footerMenu = \common\models\Menu::findDefaultFooterMenu();
$navMenu = $footerMenu ?: (\common\models\Menu::findDefaultHeaderMenu() ?: \common\models\Menu::findDefaultTopBarMenu());
if ($navMenu) {
	$builder = new \common\widgets\sitemenu\SiteNav(['items' => []]);
	$flatten = function ($items) use (&$flatten) {
		$flat = [];
		foreach ($items as $item) {
			$children = $item['items'] ?? [];
			unset($item['items']);
			$flat[] = $item;
			$flat = array_merge($flat, $flatten($children));
		}
		return $flat;
	};
	$footerItems = $flatten($builder->buildItems($navMenu->getNestedItems(null, \common\models\MenuItem::STATUS_ACTIVE)));
}
if (!$footerMenu) {
	array_unshift($footerItems, ['label' => Yii::t('yii', 'Home'), 'url' => ['/site/index']]);
}

// The legal pages, then contact, close the links column.
foreach (['/site/terms-and-conditions', '/site/privacy-policy', '/site/cookie-policy', '/site/contact'] as $legalRoute) {
	if ($legalPage = \common\models\Page::findPageByRoute([$legalRoute])) {
		$footerItems[] = ['label' => Html::encode($legalPage->translation->title), 'url' => [$legalPage->getRoute()]];
	}
}

// The scrolling band at the top of the footer: the company motto.
$marquee = [
	'AXCORP',
	Yii::t('frontend', 'Conectăm afaceri'),
	Yii::t('frontend', 'Susținem creșterea'),
];

$logoAlt = Url::to('@web/img/logo-alt.png') . '?v=' . @filemtime(Yii::getAlias('@webroot/img/logo-alt.png'));
?>
		<!-- Footer -->
		<footer class="footer">
			<div class="image-bg">
				<img loading="lazy" width="1920" height="780" src="<?= Url::to('@web/theme/images/section/bg-footer.jpg') ?>" alt="">
			</div>
			<div class="top-footer">
				<div class="infiniteSlide-app">
					<div class="infiniteSlide infiniteSlide-wrapper tf-marquee" data-clone="3">
						<?php foreach (array_merge($marquee, $marquee) as $text): ?>
							<div class="infiniteSlide-slide">
								<div class="marquee-item">
									<div class="marque-text-item">
										<i class="icon-sparkle"></i>
										<span class="h1 fw-7 letter-space--5 lh-72"><?= Html::encode(strip_tags($text)) ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="line-footer">
				<div class="container">
					<span class="dot dot-1"></span>
					<span class="dot dot-2"></span>
				</div>
			</div>
			<div class="middle-footer">
				<div class="container">
					<div class="row rg-30">
						<div class="col-xl-4">
							<div class="about-footer">
								<a href="<?= Url::to(['/site/index']) ?>" class="logo-footer">
									<img src="<?= Url::to(Yii::$app->settings->get('appLogoAlt')) ?: $logoAlt ?>" alt="<?= Yii::$app->name ?>" class="site-logo">
								</a>
								<p class="title text-title-3 fw-6 letter-space--3">
									<?= Yii::t('frontend', 'Construim conexiuni. Dezvoltăm oportunități.') ?>
									<span class="fst-italic"><?= Yii::t('frontend', 'Creăm viitorul digital.') ?></span>
								</p>
								<?php $socialLinks = array_filter([
									'icon-fb' => [$socialNetwork['facebookPage'], 'Facebook'],
									'icon-ig' => [$socialNetwork['instagramPage'], 'Instagram'],
									'icon-x' => [$socialNetwork['twitterPage'], 'Twitter'],
									'icon-in' => [$socialNetwork['linkedinPage'], 'Linkedin'],
								], function ($link) {
									return !empty($link[0]);
								}); ?>
								<?php if ($socialLinks): ?>
									<ul class="footer-social d-flex g-10 mb-30">
										<?php foreach ($socialLinks as $icon => $link): ?>
											<li><a href="<?= $link[0] ?>" title="<?= $link[1] ?>" target="_blank"><i class="<?= $icon ?>"></i></a></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						</div>
						<div class="col-xl-8">
							<div class="list-footer-col-block d-flex justify-content-between g-30">
								<?php if ($footerItems): ?>
									<div class="footer-content footer-col-block footer-content-1">
										<div class="title-mobile h4 letter-space--2 fw-5">
											<?= Yii::t('frontend', 'Quick Links') ?>
											<i class="icon-chevron-down d-sm-none d-block"></i>
										</div>
										<div class="tf-collapse-content">
											<ul class="content">
												<?php foreach ($footerItems as $item): ?>
													<?php
													$linkOptions = $item['linkOptions'] ?? [];
													Html::addCssClass($linkOptions, 'ff-2');
													?>
													<li class="support-item-footer"><?= Html::a($item['label'], $item['url'] === '#' ? '#' : Url::to($item['url']), $linkOptions) ?></li>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>
								<?php endif; ?>

								<?php if ($contact['email'] || $phone || $address): ?>
									<div class="footer-content footer-col-block footer-content-1">
										<div class="title-mobile h4 letter-space--2 fw-5">
											<?= Yii::t('frontend', 'Contact Us') ?>
											<i class="icon-chevron-down d-sm-none d-block"></i>
										</div>
										<div class="tf-collapse-content">
											<ul class="content">
												<?php if ($contact['email']): ?>
													<li class="support-item-footer"><a href="mailto:<?= $contact['email'] ?>" class="ff-2"><i class="uil uil-envelope me-2"></i><?= $contact['email'] ?></a></li>
												<?php endif; ?>
												<?php if ($phone): ?>
													<li class="support-item-footer"><a href="tel:<?= $phone ?>" class="ff-2"><i class="icon-phone-outgoing me-2"></i><?= $phone ?></a></li>
												<?php endif; ?>
												<?php if ($address): ?>
													<li class="support-item-footer">
														<?php if ($contact['latitude'] && $contact['longitude']): ?>
															<a href="//www.google.com/maps/place/<?= $contact['latitude'] ?>,<?= $contact['longitude'] ?>" target="_blank" class="ff-2"><i class="icon-map-pin me-2"></i><?= $address ?></a>
														<?php else: ?>
															<span class="ff-2"><i class="icon-map-pin me-2"></i><?= $address ?></span>
														<?php endif; ?>
													</li>
												<?php endif; ?>
											</ul>
										</div>
									</div>
								<?php endif; ?>

								<div class="footer-content footer-col-block footer-newsletter footer-content-2">
									<div class="title-mobile h4 letter-space--2 fw-5">
										<?= Yii::t('frontend', 'Subscribe To Newsletter') ?>
										<i class="icon-chevron-down d-sm-none d-block"></i>
									</div>
									<div class="tf-collapse-content">
										<div class="content">
											<?php $form = ActiveForm::begin([
												'id' => mb_strtolower($newsletterModel->formName()),
												'action' => ['/site/newsletter'],
												'options' => [
													'novalidate' => true,
													'class' => 'form-newsletter',
												],
												'validateOnType' => true,
												'validateOnBlur' => false,
											]); ?>
											<?= $form->field($newsletterModel, 'email', [
												'options' => ['class' => 'mb-3'],
												'template' => '{input}{error}',
											])->textInput([
												'type' => 'email',
												'placeholder' => Yii::t('label', 'Email'),
												'aria-label' => Yii::t('label', 'Email'),
												'required' => true,
											]) ?>
											<?= $form->field($newsletterModel, 'phone', [
												'options' => ['class' => 'position-relative'],
												'template' => '<div class="position-relative">{input}' . Html::submitButton('<i class="icon-arrow-up-right"></i>', ['class' => 'send-btn', 'title' => Yii::t('common', 'Subscribe')]) . '</div>{error}',
											])->textInput([
												'type' => 'text',
												'placeholder' => Yii::t('label', 'Phone'),
												'aria-label' => Yii::t('label', 'Phone'),
												'required' => true,
											]) ?>
											<?= $form->field($newsletterModel, 'workEmail', [
												'options' => [
													'class' => 'work-email',
												],
												'template' => '{input}',
											])->input('email', ['required' => 'required', 'autocomplete' => 'off', 'tabindex' => '-1', 'aria-hidden' => 'true'])->label(false) ?>
											<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
												<?php // Rendered explicitly on submit (not .g-recaptcha), so it never clashes with
												// a page form's widget and the token is fresh when it reaches the server. ?>
												<div id="newsletter-recaptcha" class="hidden"></div>
												<?= Html::activeHiddenInput($newsletterModel, 'captchaResponse', ['id' => 'newsletter-captcha-response']) ?>
											<?php endif; ?>
											<?php ActiveForm::end(); ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="bottom-footer">
				<div class="container">
					<div class="bottom-footer-content d-flex align-items-center justify-content-between flex-wrap">
						<p class="copy-right ff-2 text-center">
							<?= Yii::t('common', '&copy; {0} {1}. All rights reserved.', [Yii::$app->name, date('Y')]) ?>
							<?= Yii::t('common', 'Developed by {0}.', 'AXCORP SRL') ?>
						</p>
						<ul class="d-flex align-items-center justify-content-center g-15 flex-wrap">
							<li><a href="#cookiebar" class="ff-2 cookiebar-open"><?= Yii::t('frontend', 'Cookie settings') ?></a></li>
						</ul>
					</div>
				</div>
			</div>
		</footer>
		<!-- ./Footer -->
	</main>

	<!-- Mobile Menu -->
	<div class="offcanvas offcanvas-start offcanvas-mobile" id="mobileMenu">
		<div class="pop-up-nav-mobile">
			<div class="canvas-header">
				<a href="<?= Url::to(['/site/index']) ?>" class="logo-site">
					<img src="<?= $logoAlt ?>" alt="<?= Yii::$app->name ?>" class="site-logo">
				</a>
				<span class="icon icon-close" data-bs-dismiss="offcanvas"></span>
			</div>
			<div class="canvas-body">
				<div class="mb-content-top">
					<ul class="nav-ul-mb" id="wrapper-menu-navigation"></ul>
				</div>
			</div>
			<div class="canvas-bottom"></div>
		</div>
	</div>
	<!-- /Mobile Menu -->

	<!-- Go Top -->
	<div class="progress-wrap style-color-white">
		<svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
			<path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" style="transition: stroke-dashoffset 10ms linear; stroke-dasharray: 307.919, 307.919; stroke-dashoffset: 277.672"></path>
		</svg>
	</div>
	<!-- ./Go Top -->

	<div class="overlay-filter" id="overlay-filter"></div>

<?php
// Cookie consent banner. Rendered on every page: open until the visitor answers, then
// closed and reopened by the "Cookie settings" footer link. "Accept all" and "Refuse all"
// are their own forms, so the choice works without JavaScript; "Settings" opens the
// per-category form, whose boxes reflect the current choice.
$cookieCategories = [
	AcceptCookiesForm::CATEGORY_NECESSARY => [
		'label' => Yii::t('frontend', 'Necessary'),
		'description' => Yii::t('frontend', 'Always on: the session, your login and your cookie choice. The site cannot work without them.'),
	],
	AcceptCookiesForm::CATEGORY_ANALYTICS => [
		'label' => Yii::t('frontend', 'Analytics'),
		'description' => Yii::t('frontend', 'Google Analytics, to measure how the site is used. Nothing is loaded until you accept.'),
	],
	AcceptCookiesForm::CATEGORY_MARKETING => [
		'label' => Yii::t('common', 'Marketing'),
		'description' => Yii::t('frontend', 'Facebook Pixel, Facebook chat and Google Maps, for relevant ads, live chat and the map on the contact page. Nothing is loaded until you accept.'),
	],
];
// Back to the page the visitor is on: a root-relative path always passes the
// controller's same-site check, whatever host the canonical URL is set to.
$cookieBackUrl = Yii::$app->request->url;
?>
<div id="cookiebar"<?= AcceptCookiesForm::answered() ? ' class="hide"' : '' ?> role="dialog" aria-labelledby="cookiebar-title">
	<div class="container">
		<div class="row align-items-center">
			<div class="col-lg-7">
				<h3 id="cookiebar-title"><?= Yii::t('frontend', '{0} uses COOKIES', [Yii::$app->name]) ?></h3>
				<p><?= Yii::t('frontend', 'Necessary cookies keep the site working. Analytics and marketing cookies are set only if you accept them, and you can change your choice at any time from the "Cookie settings" link in the footer.') ?>
					<?= Html::a(Yii::t('common', 'Details'), ['/site/cookie-policy'], ['class' => 'cookiebar-details-link']); ?></p>
			</div>
			<div class="col-lg-5 cookiebar-actions">
				<?php $form = ActiveForm::begin(['action' => ['/accept-cookies'], 'id' => 'accept-cookies-form', 'options' => ['class' => 'cookiebar-form']]); ?>
					<?php foreach (AcceptCookiesForm::categories() as $category): ?>
						<?= Html::hiddenInput('acceptCookies[]', $category); ?>
					<?php endforeach; ?>
					<?= Html::hiddenInput('backUrl', $cookieBackUrl); ?>
					<?= Html::submitButton(Yii::t('frontend', 'Accept all'), ['class' => 'cookiebar-btn']) ?>
				<?php ActiveForm::end() ?>
				<?php $form = ActiveForm::begin(['action' => ['/accept-cookies'], 'id' => 'refuse-cookies-form', 'options' => ['class' => 'cookiebar-form']]); ?>
					<?= Html::hiddenInput('acceptCookies[]', AcceptCookiesForm::CATEGORY_NECESSARY); ?>
					<?= Html::hiddenInput('backUrl', $cookieBackUrl); ?>
					<?= Html::submitButton(Yii::t('frontend', 'Refuse all'), ['class' => 'cookiebar-btn']) ?>
				<?php ActiveForm::end() ?>
				<?= Html::button(Yii::t('common', 'Settings'), ['class' => 'cookiebar-btn', 'id' => 'cookiebar-settings-toggle', 'aria-controls' => 'cookiebar-settings', 'aria-expanded' => 'false']) ?>
			</div>
		</div>
		<div class="cookiebar-settings" id="cookiebar-settings" style="display: none;">
			<?php $form = ActiveForm::begin(['action' => ['/accept-cookies'], 'id' => 'cookie-settings-form']); ?>
				<?= Html::hiddenInput('backUrl', $cookieBackUrl); ?>
				<?php foreach ($cookieCategories as $category => $texts): ?>
					<?php $necessary = $category === AcceptCookiesForm::CATEGORY_NECESSARY; ?>
					<div class="cookiebar-category">
						<label class="form-check">
							<?= Html::checkbox('acceptCookies[]', $necessary || AcceptCookiesForm::allows($category), ['value' => $category, 'disabled' => $necessary, 'class' => 'form-check-input']) ?>
							<strong class="form-check-label"><?= $texts['label'] ?></strong>
						</label>
						<p><?= $texts['description'] ?></p>
					</div>
				<?php endforeach; ?>
				<?php // A disabled box is not posted: the necessary category travels on its own. ?>
				<?= Html::hiddenInput('acceptCookies[]', AcceptCookiesForm::CATEGORY_NECESSARY); ?>
				<?= Html::submitButton(Yii::t('frontend', 'Save settings'), ['class' => 'cookiebar-btn']) ?>
			<?php ActiveForm::end() ?>
		</div>
	</div>
</div>
<?php
$this->registerJs(<<<'JS'
	jQuery('#cookiebar-settings-toggle').on('click', function () {
		var open = !jQuery('#cookiebar-settings').is(':visible');
		jQuery('#cookiebar-settings').toggle(open);
		jQuery(this).attr('aria-expanded', open);
	});
	jQuery(document).on('click', '.cookiebar-open', function (e) {
		e.preventDefault();
		jQuery('#cookiebar').removeClass('hide');
		jQuery('#cookiebar-settings').show();
		jQuery('#cookiebar-settings-toggle').attr('aria-expanded', true);
	});
JS);
?>
<?php if (Yii::$app->settings->get('reCaptchaSiteKey', 'general')): ?>
<?php
// The newsletter form sits on every page, so its invisible reCAPTCHA is rendered and
// executed only when the visitor submits: the token is fresh (they expire after two
// minutes) and the page's own form widget, executed on load by _scripts.php, is untouched.
$this->registerJs('
(function () {
	var $form = $(' . json_encode('#' . mb_strtolower($newsletterModel->formName())) . ');
	var $token = $("#newsletter-captcha-response");
	var widgetId = null;
	var pending = false;

	$form.on("beforeSubmit", function () {
		if ($token.val()) {
			return true;
		}
		if (typeof grecaptcha === "undefined" || typeof grecaptcha.render !== "function") {
			// The script has not loaded yet; the server rejects the tokenless submission.
			return true;
		}
		if (widgetId === null) {
			widgetId = grecaptcha.render("newsletter-recaptcha", {
				sitekey: ' . json_encode(Yii::$app->settings->get('reCaptchaSiteKey', 'general')) . ',
				size: "invisible",
				badge: "inline",
				callback: function (response) {
					$token.val(response);
					if (pending) {
						pending = false;
						$form.yiiActiveForm("submitForm");
					}
				},
				"expired-callback": function () {
					$token.val("");
				}
			});
		} else {
			grecaptcha.reset(widgetId);
		}
		pending = true;
		grecaptcha.execute(widgetId);
		return false;
	});
})();
');
?>
<?php endif; ?>
