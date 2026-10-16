<?php

namespace frontend\controllers;

use common\models\ActivateAccountForm;
use common\models\Affiliate;
use common\filters\RateLimit;
use common\helpers\CaptchaHelper;
use common\helpers\RoleHelper;
use common\models\Article;
use common\models\ArticleCategory;
use common\models\ArticleCategoryTranslation;
use common\models\ArticleTranslation;
use common\models\AssumeIdentityForm;
use common\models\AuthenticateByTokenForm;
use common\models\Contractor;
use common\models\Integration;
use common\models\Language;
use common\models\LoginForm;
use common\models\Page;
use common\models\PageTranslation;
use common\models\PaymentMetadata;
use common\models\ResetPasswordForm;
use common\models\ResetPasswordRequestForm;
use common\models\Service;
use common\models\ServiceCategory;
use common\models\ServiceCategoryTranslation;
use common\models\ServiceTranslation;
use common\models\SignupForm;
use common\models\Subscription;
use common\services\SubscriptionPaymentService;
use common\models\Testimonial;
use common\models\TwoFactorAuthenticationForm;
use common\models\User;
use dosamigos\google\maps\LatLng;
use dosamigos\google\maps\Map;
use dosamigos\google\maps\overlays\InfoWindow;
use dosamigos\google\maps\overlays\Marker;
use frontend\models\AcceptCookiesForm;
use frontend\models\NewsletterForm;
use frontend\models\SpvForm;
use frontend\modules\account\models\PaymentResult;
use tws\helpers\StringHelper;
use tws\helpers\Url;
use tws\widgets\sitemap\Sitemap;
use Yii;
use frontend\models\ContactForm;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\Response;
use Zelenin\Feed;
use Zelenin\yii\extensions\Rss\RssView;

/**
 * Site controller
 */
class SiteController extends MainController
{
	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			// Per-IP throttles on the unauthenticated endpoints. The captcha covers the
			// browser case; this covers the scripted one, which is where credential
			// stuffing and mail/SMS flooding actually come from.
			'rateLimitCredentials' => [
				'class' => RateLimit::class,
				'only' => ['login', 'signup', 'reset-password', 'activate', 'confirm-login', 'assume-identity'],
				'limit' => 10,
				'window' => 900,
				// Also counted per account named, which is the shape an address counter
				// cannot see: many addresses working on one login, or one inbox being
				// flooded with reset mail.
				'identityParams' => ['username', 'email', 'phone'],
				'identityLimit' => 10,
				'identityWindow' => 3600,
				'keyPrefix' => 'ratelimit:credentials',
			],
			// These each send a mail on submission, so they are throttled on their own
			// counter and over a longer window.
			'rateLimitMessaging' => [
				'class' => RateLimit::class,
				'only' => ['contact', 'newsletter'],
				'limit' => 10,
				'window' => 3600,
				'identityParams' => ['email', 'phone'],
				'identityLimit' => 5,
				'identityWindow' => 3600,
				'keyPrefix' => 'ratelimit:messaging',
			],
			// The token travels in the URL of a GET, so the GETs themselves are what a
			// guessing script sends; the limiter above only counts submissions.
			'rateLimitTokenLinks' => [
				'class' => RateLimit::class,
				'only' => ['assume-identity', 'activate', 'reset-password'],
				// Every page view counts here, the plain form pages included, hence above 10.
				'limit' => 30,
				'window' => 900,
				'countSafeRequests' => true,
				'keyPrefix' => 'ratelimit:token-links',
			],
			'verbs' => [
				'class' => VerbFilter::class,
				'actions' => [
					'logout' => ['post'],
				],
			],
			'access' => [
				'class' => AccessControl::class,
				// Was only logout, which left the qr/spv rules below unenforced.
				'only' => ['logout', 'qr', 'spv'],
				'rules' => [
					[
						'actions' => ['logout'],
						'allow' => true,
						'roles' => ['@'],
					],
					[
						'actions' => ['qr'],
						'allow' => true,
						'roles' => ['@'],
					],
					[
						'actions' => ['spv'],
						'allow' => true,
						'roles' => ['@', '?'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
			],
		];
	}

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        // The Stripe IPN webhook is a machine-to-machine POST with no CSRF token (Stripe's
        // signature authenticates it instead). The error action must skip validation too:
        // a rejected POST renders the error page on that same token-less request, and
        // re-validating there raises a second exception ("error while handling another
        // error", HTTP 500) instead of the clean 400 the rejection should produce.
        if (in_array($action->id, ['ipn', 'error'], true)) {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

	/**
	 * Displays homepage.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$this->layout = 'home';

		return $this->render('index');
	}

	/**
	 * Subscribes an email to the newsletter.
	 *
	 * @return mixed
	 */
	public function actionNewsletter()
	{
		$newsletterModel = new NewsletterForm();
		if ($newsletterModel->load(Yii::$app->request->post()) && $newsletterModel->validate()) {
			if ($newsletterModel->model()) {
				Yii::$app->session->setFlash('success', Yii::t('frontend', 'Thank you for subscribing to our newsletter. From now on you are closer to the news you will receive from us.'));
			} else {
				$errors = $newsletterModel->getFirstErrors();
				$errorMessage = !empty($errors) ? reset($errors) : Yii::t('common', 'There was an error saving your subscription.');
				Yii::$app->session->setFlash('error', $errorMessage);
			}
			return $this->redirect(['index']);
		}

		return $this->render('index');
	}

	/**
	 * Displays about us page.
	 *
	 * @return mixed
	 */
	public function actionAboutUs()
	{
		return $this->render('about-us');
	}

	/**
	 * Displays contact page.
	 *
	 * @return mixed
	 */
	public function actionContact()
	{
		$model = new ContactForm();

		if (!Yii::$app->user->isGuest) {
			$user = Yii::$app->user->identity;
			$model->name = $user->getFullName();
			$model->email = $user->email;
			$model->phone = $user->phone;
		}
		if (Yii::$app->request->get('request') == 'enterprise') {
			$model->subject = Yii::t('frontend', 'Request for Enterprise package');
			$model->message = Yii::t('frontend', "Hello,\n\nI am interested in subscribing to an Enterprise package.\nMy requirements are: 5 workspaces and 10 users.\n\nThank You.");
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->sendEmail()) {
				Yii::$app->session->setFlash('success', Yii::t('frontend', 'Thank you for contacting us. We will respond to you as soon as possible.'));
			} else {
				Yii::$app->session->setFlash('error', Yii::t('common', 'There was an error sending your message.'));
			}
			return $this->redirect(['contact']);
		}

		$contact = Yii::$app->settings->getCategory('contact');
		$address = implode(', ', array_filter([
			$contact['streetName'],
			$contact['streetNumber'],
			$contact['locality'],
			$contact['zipCode'],
			$contact['county'],
			$contact['country'] ? \common\models\Country::findAllCountries()[$contact['country']]->translation->name : null,
		]));
		if ($contact['latitude'] && $contact['longitude']) {
			$latLng = new LatLng([
				'lat' => $contact['latitude'],
				'lng' => $contact['longitude'],
			]);
			$map = new Map([
				'center' => $latLng,
				'zoom' => 17,
				'width' => '100%',
				'height' => 400,
				'mapTypeControl' => false,
			]);
			$marker = new Marker([
				'position' => $latLng,
				'title' => Yii::$app->name,
//			'icon' => Yii::getAlias('@web/img/map-marker.png'),
			]);
			if ($address) {
				$marker->attachInfoWindow(
					new InfoWindow([
						'content' => $address,
					])
				);
			}
			$map->addOverlay($marker);
		}

		return $this->render('contact', [
			'model' => $model,
			'contact' => $contact,
			'address' => $address,
			'map' => $map,
		]);
	}


	/**
	 * Displays terms and conditions page.
	 *
	 * @return mixed
	 */
	public function actionTermsAndConditions()
	{
		return $this->render('terms-and-conditions');
	}

	/**
	 * Displays privacy policy page.
	 *
	 * @return mixed
	 */
	public function actionPrivacyPolicy()
	{
		return $this->render('privacy-policy');
	}

    /**
     * Displays cookie policy page.
     *
     * @return mixed
     */
    public function actionCookiePolicy()
    {
        return $this->render('cookie-policy');
    }




	/**
	 * Logs in a user.
	 *
	 * @return mixed
	 */
	public function actionLogin()
	{
		if (!Yii::$app->user->isGuest) {
			Yii::$app->session->setFlash('info', Yii::t('common', 'You are already authenticated.'));
			return $this->goHome();
		}

		$model = new LoginForm();

		if ($model->load(Yii::$app->request->post())) {
			if ($model->login()) {
				return $this->goHome();
			}
			Yii::$app->session->setFlash('error', Yii::t('common', 'The provided credentials are invalid.'));
		}

		return $this->render('login', [
			'model' => $model,
		]);
	}

	/**
	 * Login as user.
	 *
	 * @return mixed
	 */
	public function actionAssumeIdentity($id)
	{
		if (!Yii::$app->user->isGuest || empty($id)) {
			return $this->goHome();
		}

		$model = new AssumeIdentityForm();
		// Honeypot, captcha and remember-me come from the posted form; the id only ever
		// from the signed token, so it is set after load().
		$model->load(Yii::$app->request->post());
		$model->id = AssumeIdentityForm::resolveToken((string) $id);

		// With reCAPTCHA on, the GET only renders the form; the login happens on its submit.
		if (Yii::$app->request->isGet && CaptchaHelper::isEnabled()) {
			return $this->render('assume-identity', [
				'model' => $model,
			]);
		}

		if ($model->login()) {
			return $this->goBack(['/site/index']);
		} else {
			$message = Yii::t('common', 'The provided credentials are invalid.');
			if ($model->id && ($user = User::findOne(['id' => $model->id, 'deleted' => User::NO]))) {
				if ($user->status == User::STATUS_INACTIVE && Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_AUTOMATIC) {
					$message = Yii::t('common', 'Your account is inactive. Please make sure you have activated your account by clicking the link in the activation email you received.');
				}
			}
			Yii::$app->session->setFlash('error', $message);
		}

		return $this->render('assume-identity', [
			'model' => $model,
		]);
	}

	/**
	 * Logs out the current user.
	 *
	 * @return mixed
	 */
	public function actionLogout()
	{
		/** @var User $user */
		$user = Yii::$app->user->identity;

		Yii::$app->user->logout();

		// Ending the session is not enough: the same auth_key is the REST API's bearer
		// token, so without rotating it a signed-out account stays reachable through the
		// API by anyone holding the token. It also invalidates any "remember me" cookie,
		// which is what signing out is meant to do.
		if ($user !== null) {
			$user->rotateAuthKey();
		}

		return $this->goHome();
	}

	/**
	 * Signs user up.
	 *
	 * @return mixed
	 */
	public function actionSignup()
	{
		if (!Yii::$app->user->isGuest) {
			Yii::$app->session->setFlash('info', Yii::t('common', 'You already have an account.'));
			return $this->goHome();
		}

		$model = new SignupForm();

		if ($model->load(Yii::$app->request->post()) && $model->signup()) {
			Yii::$app->trigger('user.afterSignup', new \yii\base\Event(['sender' => $model->getUser()]));
			if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
				if ($model->sendActivationEmail()) {
					Yii::$app->session->setFlash('success', Yii::t('common', 'Please check your email to activate your account.'));
				} else {
					Yii::$app->session->setFlash('error', Yii::t('common', 'Cannot send the activation message.'));
				}
				return $this->redirect(['/site/index']);
			} else {
				Yii::$app->session->setFlash('success', Yii::t('common', 'Your account was successfully created.'));
				if (Yii::$app->getUser()->login($model->getUser(), (int) Yii::$app->settings->get('userLoginDuration'))) {
					return $this->goHome();
				}
				return $this->redirect(['/site/index']);
			}
		}

		return $this->render('signup', [
			'model' => $model,
		]);
	}

	/**
	 * Activates user account.
	 *
	 * @param string|int|null $token
	 * @return mixed
	 */
	public function actionActivate($token = null)
	{
		if (!Yii::$app->user->isGuest) {
			Yii::$app->session->setFlash('info', Yii::t('common', 'Your account is already active.'));
			return $this->goHome();
		}

		$model = new ActivateAccountForm();
		$model->token = $token;

		if (($model->load(Yii::$app->request->post()) || !empty($token)) && $model->validate()) {
			if ($model->activate()) {
				Yii::$app->trigger('user.afterAccountActivation', new \yii\base\Event(['sender' => $model->getUser()]));
				Yii::$app->session->setFlash('success', Yii::t('common', 'Your account has been activated.'));
				if (Yii::$app->getUser()->login($model->getUser(), (int) Yii::$app->settings->get('userLoginDuration'))) {
					if ($model->getUser()->getIsSubscriber() && $model->getUser()->workspaces[0]) {
						return $this->redirect($model->getUser()->workspaces[0]->getAbsoluteUrl());
					}
					return $this->redirect(['/site/index']);
				}
				return $this->redirect(['/site/login']);
			}
			Yii::$app->session->setFlash('error', Yii::t('common', 'Cannot activate the account due to invalid data provided.'));
		}

		return $this->render('activate', [
			'model' => $model,
		]);
	}

	/**
	 * Resets password.
	 *
	 * @param null|string $token
	 * @return mixed
	 */
	public function actionResetPassword($token = null)
	{
		if (!Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$resetPasswordRequestModel = new ResetPasswordRequestForm();
		$resetPasswordModel = new ResetPasswordForm();
		$resetPasswordModel->token = $token;
		$resetPasswordModel->setScenario(ResetPasswordForm::SCENARIO_TOKEN);

		if ($resetPasswordRequestModel->load(Yii::$app->request->post()) && $resetPasswordRequestModel->validate() && $resetPasswordRequestModel->sendRequest()) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'You have requested a password reset. Please check the message that was sent to {0}.', [$resetPasswordRequestModel->username]));
			return $this->redirect(['reset-password']);
		} else {
			$resetPasswordModel->load(Yii::$app->request->post());
			// Switch to enter password scenario if the provided token is valid
			if (!empty($resetPasswordModel->token) && $resetPasswordModel->getUser()) {
				$resetPasswordModel->setScenario(ResetPasswordForm::SCENARIO_PASSWORD);
				if ($resetPasswordModel->token != $token) {
					return $this->redirect(['reset-password', 'token' => $resetPasswordModel->token]);
				}
			}
			if ($resetPasswordModel->load(Yii::$app->request->post()) && $resetPasswordModel->validate() && $resetPasswordModel->resetPassword()) {
				Yii::$app->session->setFlash('success', Yii::t('common', 'New password saved.'));
				if ($user = $resetPasswordModel->getUser()) {
					$user = is_array($user) ? reset($user) : $user;
					if (Yii::$app->getUser()->login($user, (int) Yii::$app->settings->get('userLoginDuration'))) {
						return $this->goHome();
					}
				}
			}
		}

		return $this->render('reset-password', [
			'resetPasswordRequestModel' => $resetPasswordRequestModel,
			'resetPasswordModel' => $resetPasswordModel,
		]);
	}

	/**
	 * Confirm Login.
	 *
	 * @param null|string $token
	 * @return mixed
	 */
	public function actionConfirmLogin($token = null)
	{
		if (Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$twoFactorAuthenticationModel = new TwoFactorAuthenticationform();
		$twoFactorAuthenticationModel->token = $token;

		if ($twoFactorAuthenticationModel->load(Yii::$app->request->post()) && $twoFactorAuthenticationModel->validate() && $twoFactorAuthenticationModel->confirmLogin()) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'Login confirmed.'));
			return $this->goBack();
		}

		return $this->render('confirm-login', [
			'twoFactorAuthenticationModel' => $twoFactorAuthenticationModel,
		]);
	}



    /**
     * Site accept cookies action.
     *
     * @return mixed
     */
    public function actionAcceptCookies()
    {
        $model = new AcceptCookiesForm();
        $model->acceptCookies = Yii::$app->request->post('acceptCookies');
        $model->backUrl = Yii::$app->request->post('backUrl');
        if ($model->validate()) {
            $model->setCookies();
        }
        if ($this->isLocalUrl($model->backUrl)) {
            return $this->redirect($model->backUrl);
        }
        return $this->redirect(Url::base(true) . '/' . (Yii::$app->settings->get('defaultLanguage') == Yii::$app->language ? '' :  mb_substr(Yii::$app->language, 0, 2)));
    }

	/**
	 * Whether a posted return URL stays on this site: a path, or an absolute URL on this
	 * host. Anything else would make accept-cookies an open redirect.
	 *
	 * @param mixed $url
	 * @return bool
	 */
	protected function isLocalUrl($url)
	{
		if (!is_string($url) || $url === '' || preg_match('/[\x00-\x1f\\\\]/', $url)) {
			return false;
		}
		if ($url[0] === '/') {
			// "//host" is protocol-relative, i.e. another site.
			return !isset($url[1]) || $url[1] !== '/';
		}
		$parts = parse_url($url);
		return $parts !== false
			&& isset($parts['scheme'], $parts['host'])
			&& in_array(strtolower($parts['scheme']), ['http', 'https'], true)
			&& strcasecmp($parts['host'], (string) parse_url(Yii::$app->request->hostInfo, PHP_URL_HOST)) === 0;
	}

	/**
	 * Displays rss page.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function actionRss()
	{
		$response = Yii::$app->getResponse();
		$response->format = Response::FORMAT_RAW;

		$headers = $response->getHeaders();
		$headers->set('Content-Type', 'application/rss+xml; charset=utf-8');

		$dataProvider = new ActiveDataProvider([
			'query' => Article::provideRssArticles(),
			'pagination' => [
				'pageSize' => 10,
			],
		]);

		return RssView::widget([
			'dataProvider' => $dataProvider,
			'channel' => [
				'title' => function ($widget, Feed $feed) {
					$feed->addChannelTitle(Yii::$app->name);
				},
				'link' => Url::to(['/site/index'], true),
				'description' => Yii::t('common', 'Articles'),
				'language' => function ($widget, Feed $feed) {
					return Yii::$app->language;
				},
				'image'=> function ($widget, Feed $feed) {
					if (!($logoUrl = Url::to(Yii::$app->settings->get('appLogo')))) {
						$logoUrl = Url::to('@web/img/logo.png', true);
					}
					$feed->addChannelImage($logoUrl, Url::home(true), 144, 36, Yii::$app->name);
				},
			],
			'items' => [
				'title' => function (Article $model, $widget, Feed $feed) {
					return $model->translation->title;
				},
				'description' => function (Article $model, $widget, Feed $feed) {
					return StringHelper::truncateWords($model->translation->description, 50);
				},
				'link' => function (Article $model, $widget, Feed $feed) {
					return Url::to(['/article/default/view', 'slug' => $model->translation->slug], true);
				},
				'guid' => function (Article $model, $widget, Feed $feed) {
					return Url::to(['/article/default/view', 'slug' => $model->translation->slug], true);
				},
				'pubDate' => function (Article $model, $widget, Feed $feed) {
					return (new \DateTime($model->created_at))->format(DATE_RSS);
				},
			],
		]);
	}

    /**
     * Stripe webhook (IPN).
     *
     * Two jobs. `checkout.session.completed` finishes a first purchase even when the buyer
     * never lands on the result page. `invoice.paid` with billing_reason=subscription_cycle
     * is the renewal fast path: Stripe collected the cycle invoice on its own schedule and
     * tells us right away, without waiting for the payment/run polling, which stays in
     * place as the reconciliation net. The atomic claim inside settleRenewal() keeps the
     * two paths from double-settling.
     *
     * @return mixed
     */
    public function actionIpn()
    {
	    if (!Yii::$app->request->isPost) {
		    return $this->redirect(['index']);
	    }

	    $paymentSettings = Yii::$app->settings->getCategory('payment');
	    $paymentProcessors = (array) ($paymentSettings['paymentProcessors'][PaymentMetadata::PAYMENT_METHOD_CARD] ?? []);
	    $model = new PaymentResult();

	    $params = Yii::$app->request->post();
	    if (!array_key_exists(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE, $paymentProcessors) || empty($params)) {
		    $model->addError('', Yii::t('frontend', 'Payment response is not valid.'));
	    }

	    $endpoint_secret = $paymentSettings['stripeWebhookIPNKey'] ?? '';
	    $payload = @file_get_contents('php://input');
	    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
	    $event = null;

	    try {
		    $event = \Stripe\Webhook::constructEvent(
			    $payload, $sig_header, $endpoint_secret
		    );
	    } catch (\UnexpectedValueException $e) {
		    // Invalid payload
		    http_response_code(400);
		    exit();
	    } catch (\Stripe\Exception\SignatureVerificationException $e) {
		    // Invalid signature
		    http_response_code(400);
		    exit();
	    }

	    switch ($event->type) {
		    case 'checkout.session.async_payment_failed':
		    case 'checkout.session.expired':
			    $model->addError('', Yii::t('frontend', 'Payment failed.'));
			    break;
		    case 'checkout.session.async_payment_succeeded':
		    case 'checkout.session.completed':
			    $attributes = [];
			    $session = $event->data->object;
			    $payment = Yii::$app->payment->via(PaymentMetadata::PAYMENT_PROCESSOR_STRIPE);
			    $stripe = new \Stripe\StripeClient(
				    $payment->privateKey
			    );
			    if (!empty($session) && $session['payment_status'] == 'paid') {
				    $attributes = [
					    'subscription_id' => $session['metadata']['subscription_id'],
					    'payer_id' => $session['customer'],
				    ];

				    if ($charge = PaymentResult::findSessionChargeId($stripe, $session)) {
					    $attributes['payment_id'] = $charge;
				    }

				    if ($subscription = Subscription::findOne(['id' => $session['metadata']['subscription_id']])) {
					    if (empty($subscription->paymentMetadata)) {
						    $paymentMetadata = new PaymentMetadata();
						    $paymentMetadata->subscription_id = $subscription->id;
						    $paymentMetadata->payment_processor = PaymentMetadata::PAYMENT_PROCESSOR_STRIPE;
						    $paymentMetadata->payment_method = PaymentMetadata::PAYMENT_METHOD_CARD;
						    $paymentMetadata->recurring_payment = PaymentMetadata::YES;
						    $paymentMetadata->status = PaymentMetadata::STATUS_ACTIVE;
						    $paymentMetadata->save(false);
					    }
					    // The Stripe subscription id, same as the result page saves it. Without it
					    // the renewal webhook and the cancel flow have nothing to match on Stripe's
					    // side — and the buyer may never land on the result page.
					    if (!empty($session['subscription']) && $subscription->external_id !== $session['subscription']) {
						    $subscription->external_id = $session['subscription'];
						    $subscription->save(false);
					    }
				    }

				    $model->load($attributes, '');
				    $model->save();
				    if ($model->subscription_id && $subscription = Subscription::findOne(['id' => $model->subscription_id, 'status' => [Subscription::STATUS_INACTIVE, Subscription::STATUS_SUSPENDED, Subscription::STATUS_INCOMPLETE]])) {
					    $subscription->status = Subscription::STATUS_ACTIVE;
					    $subscription->save(false);
				    }
			    }
			    break;
		    case 'invoice.payment_succeeded':
		    case 'invoice.paid':
			    $stripeInvoice = $event->data->object;
			    // First payments carry billing_reason=subscription_create and belong to the
			    // checkout.session.completed flow above; anything but a cycle renewal is
			    // acknowledged and ignored.
			    if (($stripeInvoice['billing_reason'] ?? '') !== 'subscription_cycle') {
				    break;
			    }
			    // The Stripe subscription id. API versions from 2025-03-31 (basil) moved it
			    // from invoice.subscription to invoice.parent.subscription_details.subscription;
			    // older webhook endpoint versions still send it at the root.
			    $stripeSubscriptionId = $stripeInvoice['parent']['subscription_details']['subscription']
				    ?? $stripeInvoice['subscription']
				    ?? null;
			    if (!is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
				    Yii::warning([
					    'message' => 'IPN invoice.paid: subscription_cycle invoice without a subscription id, not settling.',
					    'invoice' => $stripeInvoice['id'] ?? null,
				    ], __METHOD__);
				    break;
			    }
			    $subscription = Subscription::findOne([
				    'external_id' => $stripeSubscriptionId,
				    'deleted' => Subscription::NO,
			    ]);
			    if (!$subscription) {
				    // Pre-external_id history — the polling reconciliation still covers it.
				    Yii::warning('IPN invoice.paid: no local subscription for ' . $stripeSubscriptionId, __METHOD__);
				    break;
			    }
			    // end_at already in the future means this cycle is settled locally (the
			    // polling won the race, or Stripe redelivered the event).
			    if (strtotime((string) $subscription->end_at) > time()) {
				    break;
			    }

			    SubscriptionPaymentService::setCurrentDate();
			    // Get-or-create keeps the invariant "one open proforma per subscription":
			    // normally the advance proforma exists already; a webhook arriving before the
			    // issuance window just creates it on the spot.
			    $localInvoice = SubscriptionPaymentService::saveInvoice($subscription);
			    if (!$localInvoice) {
				    break;
			    }
			    // The money collected must match the proforma; on a mismatch leave the
			    // invoice open and let the polling (or a human) sort it out.
			    if ((int) round($localInvoice->amount * 100) !== (int) ($stripeInvoice['amount_paid'] ?? -1)
				    || strcasecmp((string) $localInvoice->currency, (string) ($stripeInvoice['currency'] ?? '')) !== 0) {
				    Yii::warning([
					    'message' => 'IPN invoice.paid: amount/currency mismatch, not settling.',
					    'subscription' => $subscription->code,
					    'local' => $localInvoice->amount . ' ' . $localInvoice->currency,
					    'stripe' => ($stripeInvoice['amount_paid'] ?? '?') . ' ' . ($stripeInvoice['currency'] ?? '?'),
				    ], __METHOD__);
				    break;
			    }

			    // The processor's payment reference, best effort across API versions: the
			    // charge id, else the payment intent (both gone from the invoice since basil),
			    // else the Stripe invoice id, which is always present.
			    $transactionId = is_string($stripeInvoice['charge'] ?? null) ? $stripeInvoice['charge'] : ($stripeInvoice['charge']['id'] ?? null);
			    $transactionId = $transactionId ?: (is_string($stripeInvoice['payment_intent'] ?? null) ? $stripeInvoice['payment_intent'] : null);
			    $transactionId = $transactionId ?: (string) $stripeInvoice['id'];

			    // The cycle the Stripe invoice bills: the subscription's new period.
			    $period = $stripeInvoice['lines']['data'][0]['period'] ?? [];
			    SubscriptionPaymentService::settleRenewal(
				    $subscription,
				    $localInvoice,
				    $transactionId,
				    SubscriptionPaymentService::formatProcessorTimestamp($period['start'] ?? null),
				    SubscriptionPaymentService::formatProcessorTimestamp($period['end'] ?? null)
			    );
			    break;
		    default:
			    // Events subscribed to but not acted on fall through and still get the 200
			    // below, otherwise Stripe marks the delivery failed and keeps retrying.
			    break;
	    }

	    http_response_code(200);
    }

	/**
	 * Displays sitemap page.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function actionSitemap()
	{
		$response = Yii::$app->getResponse();
		$response->format = Response::FORMAT_RAW;

		$headers = $response->getHeaders();
		$headers->set('Content-Type', 'application/xml; charset=utf-8');

		$servicesPage = Page::findPageByRoute(['/service/default/index']);
		/** @var \common\models\PageTranslation[] $servicesPageTranslations */
		$servicesPageTranslations = $servicesPage->getPageTranslations()
            ->where([
                'language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id')
            ])
            ->indexBy('language_id')
            ->all();

		$articlesPage = Page::findPageByRoute(['/article/default/index']);
		/** @var \common\models\PageTranslation[] $servicesPageTranslations */
		$articlesPageTranslations = $articlesPage->getPageTranslations()
            ->where([
                'language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id')
            ])
            ->indexBy('language_id')
            ->all();

        $pagesDataProvider = new ActiveDataProvider([
            'query' => Page::find()
                ->alias('p')
                ->select([
                    'p.*',
                ])
                ->joinWith([
                    'pageTranslations pt' => function (ActiveQuery $query) {
                        return $query->andOnCondition([
                            'pt.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                            'pt.deleted' => PageTranslation::NO,
                        ]);
                    },
                ])
                ->andWhere([
                    'OR',
                    ['IS', 'p.module', null],
                    ['!=', 'p.module', 'account'],
                ])
                ->andWhere(['!=', 'p.action', 'subscribe'])
                ->andWhere([
                    'p.status' => Page::STATUS_ACTIVE,
                    'p.deleted' => Page::NO,
                    'pt.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                ]),
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);

        $serviceCategoriesDataProvider = new ActiveDataProvider([
            'query' => ServiceCategory::find()
                ->alias('sc')
                ->select([
                    'sc.*',
                ])
                ->joinWith([
                    'serviceCategoryTranslations sct' => function (ActiveQuery $query) {
                        return $query->andOnCondition([
                            'sct.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                            'sct.deleted' => ServiceCategoryTranslation::NO,
                        ]);
                    },
                ])
                ->where([
                    'sc.status' => ServiceCategory::STATUS_ACTIVE,
                    'sc.deleted' => ServiceCategory::NO,
                    'sct.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                ]),
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);

        $servicesDataProvider = new ActiveDataProvider([
            'query' => Service::find()
                ->alias('s')
                ->select([
                    's.*',
                ])
                ->joinWith([
                    'serviceTranslations st' => function (ActiveQuery $query) {
                        return $query->andOnCondition([
                            'st.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                            'st.deleted' => ServiceTranslation::NO,
                        ]);
                    },
                ])
                ->where([
                    's.status' => Service::STATUS_ACTIVE,
                    's.deleted' => Service::NO,
                    'st.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                ]),
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);

        $articleCategoriesDataProvider = new ActiveDataProvider([
            'query' => ArticleCategory::find()
                ->alias('ac')
                ->select([
                    'ac.*',
                ])
                ->joinWith([
                    'articleCategoryTranslations act' => function (ActiveQuery $query) {
                        return $query->andOnCondition([
                            'act.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                            'act.deleted' => ArticleCategoryTranslation::NO,
                        ]);
                    },
                ])
                ->where([
                    'ac.status' => ArticleCategory::STATUS_ACTIVE,
                    'ac.deleted' => ArticleCategory::NO,
                    'act.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                ]),
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);

        $articlesDataProvider = new ActiveDataProvider([
            'query' => Article::find()
                ->alias('a')
                ->select([
                    'a.*',
                ])
                ->joinWith([
                    'articleTranslations at' => function (ActiveQuery $query) {
                        return $query->andOnCondition([
                            'at.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                            'at.deleted' => ArticleTranslation::NO,
                        ]);
                    },
                ])
                ->where([
                    'a.status' => Article::STATUS_ACTIVE,
                    'a.deleted' => Article::NO,
                    'at.language_id' => ArrayHelper::getColumn(Language::findAllLanguages(), 'language_id'),
                ]),
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);

		$xml = Sitemap::widget([
			'items' => [
				[
					'dataProvider' => $pagesDataProvider,
					'i18n' => [
						'relation' => 'pageTranslations',
						'route' => function ($item, $model, $modelTranslation) {
							return [$modelTranslation->slug];
						},
					],
					'options' => [
						'changefreq' => Sitemap::CHANGEFREQ_YEARLY,
						'priority' => '1.0',
					],
				],
				[
					'dataProvider' => $serviceCategoriesDataProvider,
					'i18n' => [
						'relation' => 'serviceCategoryTranslations',
						'route' => function ($item, $model, $modelTranslation) use ($servicesPage, $servicesPageTranslations) {
							return [
								implode('/', [
									$servicesPageTranslations[$modelTranslation->language_id]->slug,
									Inflector::slug(Yii::t($servicesPage->module, 'Category', [], $modelTranslation->language_id)),
									$modelTranslation->slug
								]),
							];
						},
					],
					'options' => [
						'changefreq' => Sitemap::CHANGEFREQ_WEEKLY,
						'priority' => '1.0',
					],
				],
				[
					'dataProvider' => $servicesDataProvider,
					'i18n' => [
						'relation' => 'serviceTranslations',
						'route' => function ($item, $model, $modelTranslation) use ($servicesPage, $servicesPageTranslations) {
							return [
								implode('/', [
									$servicesPageTranslations[$modelTranslation->language_id]->slug,
									$modelTranslation->slug
								]),
							];
						},
					],
					'options' => [
						'changefreq' => Sitemap::CHANGEFREQ_YEARLY,
						'priority' => '1.0',
					],
				],
				[
					'dataProvider' => $articleCategoriesDataProvider,
					'i18n' => [
						'relation' => 'articleCategoryTranslations',
						'route' => function ($item, $model, $modelTranslation) use ($articlesPage, $articlesPageTranslations) {
							return [
								implode('/', [
									$articlesPageTranslations[$modelTranslation->language_id]->slug,
									Inflector::slug(Yii::t($articlesPage->module, 'Category', [], $modelTranslation->language_id)),
									$modelTranslation->slug
								]),
							];
						},
					],
					'options' => [
						'changefreq' => Sitemap::CHANGEFREQ_WEEKLY,
						'priority' => '1.0',
					],
				],
				[
					'dataProvider' => $articlesDataProvider,
					'i18n' => [
						'relation' => 'articleTranslations',
						'route' => function ($item, $model, $modelTranslation) use ($articlesPage, $articlesPageTranslations) {
							return [
								implode('/', [
									$articlesPageTranslations[$modelTranslation->language_id]->slug,
									$modelTranslation->slug
								]),
							];
						},
					],
					'options' => [
						'changefreq' => Sitemap::CHANGEFREQ_WEEKLY,
						'priority' => '1.0',
						'lastmod' => 'updated_at',
					],
				],
			],
		]);

		// Chrome/Edge have no native XML tree viewer, so a bare XML document renders as its
		// concatenated text nodes. The stylesheet makes the sitemap readable in a browser;
		// crawlers ignore the processing instruction.
		return preg_replace(
			'/^(<\?xml[^?]*\?>)/',
			'$1' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . Url::to('@web/sitemap.xsl') . '"?>',
			$xml,
			1
		);
	}


	/**
	 * Site search action.
	 *
	 * @return mixed
	 */
	public function actionSearch()
	{
		$dataset = [];
		if (!($criteria = Yii::$app->request->post('county', null)) && !($criteria = Yii::$app->request->post('locality', null))) {
			return $this->asJson($dataset);
		}

		// A locality request sends `county` as its filter, not as a second query, so
		// the county branch stays out of it - otherwise the locality suggestions also
		// offered the county's own name.
		if (!Yii::$app->request->post('locality', null) && ($criteria = Yii::$app->request->post('county', null))) {
			// array_filter drops the country when none is chosen: findByCriteria() turns
			// whatever it is handed into a WHERE, so a null country_code searched for
			// counties with country_code IS NULL and always came back empty.
			$params = array_filter([
				'country_code' => Yii::$app->request->post('country_code', null),
			]);
			$counties = \common\models\County::findByCriteria($criteria, $params);
			foreach ($counties as $county) {
				$dataset[] = [
					'label' => $county->name,
				];
			}
		}

		if ($criteria = Yii::$app->request->post('locality', null)) {
			// Same here: with no county typed, an unfiltered locality search beats one
			// that looks for localities whose county IS NULL.
			$params = array_filter([
				'county' => Yii::$app->request->post('county', null),
			]);
			$localities = \common\models\Locality::findByCriteria($criteria, $params);
			foreach ($localities as $locality) {
				$dataset[] = [
					'label' => $locality->name,
				];
			}
		}

		return $this->asJson([
			'results' => $dataset,
		]);
	}

	/**
	 * Displays any dynamic page.
	 *
	 * @return mixed
	 */
	public function actionPage()
	{
		return $this->render('page');
	}

	/**
	 *
	 * @return mixed
	 */
	public function actionSpv()
	{
		// Signed-in, only the superAdmin manages the platform integration. Guests get in only
		// with a link the superAdmin issued (the accountant holding the certificate), and on
		// the ANAF callback that follows it in the same session.
		if ((!Yii::$app->user->isGuest && !RoleHelper::isSuperAdmin()) || (Yii::$app->user->isGuest && empty(Yii::$app->request->get('authorize')) && empty(Yii::$app->request->get('code')))) {
			return $this->goHome();
		}

		$model = new SPVForm();
		$currentDate = date('Y-m-d H:i:s');
		$integration = Integration::find()
			->where([
				'type' => Integration::TYPE_SPV,
				'status' => Integration::STATUS_ACTIVE,
				'deleted' => Integration::NO,
			])
			->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[expire_at]]) > 0"))
			->one();

		if (empty($integration) && !Yii::$app->user->isGuest) {
			// Re-issued once expired (or still holding the old unsigned format).
			if (!SpvForm::verifyAuthorizeToken(Yii::$app->getRequest()->getCookies()->getValue('spv'))) {
				$model->spv = SpvForm::generateAuthorizeToken();
				if ($model->setCookies()) {
					return $this->redirect(Yii::$app->params['spv.redirect']);
				}
			} else {
				$anchor = Yii::t('frontend', 'Authorize');
				$link = implode('?', [
					Yii::$app->params['spv.redirect'],
					http_build_query([
						"authorize" => Yii::$app->getRequest()->cookies->getValue('spv'),
					]),
				]);
			}
		}

		if (!empty(Yii::$app->request->get('authorize'))) {
			$token = Yii::$app->request->get('authorize');
			if (SpvForm::verifyAuthorizeToken($token)) {
				// Ties the callback to this browser, so a code cannot be planted from elsewhere.
				$state = Yii::$app->security->generateRandomString(32);
				Yii::$app->session->set(SpvForm::STATE_SESSION_KEY, $state);
				$url = implode('?', [
					'https://logincert.anaf.ro/anaf-oauth2/v1/authorize',
					http_build_query([
						"response_type" => "code",
						"client_id" => Yii::$app->params['spv.clientId'],
						"redirect_uri" => Yii::$app->params['spv.redirect'],
						"token_content_type" => "jwt",
						"state" => $state,
					]),
				]);
				return $this->redirect($url);
			}
			if (Yii::$app->user->isGuest) {
				return $this->goHome();
			}
		}

		if (!empty(Yii::$app->request->get('code'))) {
			$state = Yii::$app->session->get(SpvForm::STATE_SESSION_KEY);
			Yii::$app->session->remove(SpvForm::STATE_SESSION_KEY);
			if (!is_string($state) || !is_string(Yii::$app->request->get('state')) || !hash_equals($state, Yii::$app->request->get('state'))) {
				Yii::$app->session->setFlash('error', Yii::t('common', 'The request is invalid or has expired.'));
				return $this->goHome();
			}
			$model = new SPVForm();
			$model->spv = Yii::$app->request->get('code');
			if (empty($integration)) {
				if (!$model->saveModel()) {
					if (!empty($model->errors)) {
						$errors = array_values($model->errors)[0];
						$message = implode('<br>', $errors);
						Yii::$app->session->setFlash('error', $message);
					}
				} else {
					Yii::$app->session->setFlash('success', Yii::t('frontend', 'The authorization of account access in S.P.V. has been successfully completed.'));
				}
			}
			return $this->goHome();
		}

		return $this->render('spv', [
			'integration' => $integration,
			'anchor' => $anchor,
			'link' => $link,
		]);
	}
}