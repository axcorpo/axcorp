<?php

namespace common\components;

use common\models\Language;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

class ApplicationBootstrap extends Component implements BootstrapInterface
{
	/**
	 * @var array The application active languages.
	 */
	public $languages = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->languages = ArrayHelper::map(Language::findAllLanguages(), 'language', 'language_id');
	}

	/**
	 * @inheritdoc
	 *
	 * @param \yii\base\Application $app
	 * @throws \yii\base\InvalidConfigException
	 * @throws \Throwable
	 */
	public function bootstrap($app)
	{
		$this->initSettings();
		$this->initMailer();
		$this->initPayment();
		$this->initCommercial();
		$this->initFormatter();

		$app->on(Application::EVENT_BEFORE_ACTION, function ($event) {
			$this->initFormatter();
		});

//        if (!($app instanceof \yii\console\Application) && Yii::$app->user->isGuest) {
//            $app->on(\yii\web\Application::EVENT_BEFORE_REQUEST, function ($event) {
//                $app = $event->sender;
//                if ($app->request->isPost && ($bodyParams = $app->request->getBodyParams())) {
//                    $app->request->setBodyParams($bodyParams);
//                }
//                if ($app->request->isGet && ($queryParams = Yii::$app->request->getQueryParams())) {
//                    $app->request->setQueryParams($queryParams);
//                }
//            });
//        }

		$app->on('invalidate.cache', function ($event) use ($app) {
			$cache = $app->cache;
			$initialCachePath = $cache->cachePath;
			$baseCachePath = FileHelper::normalizePath(dirname($app->basePath) . '/{{APP}}/runtime/cache');

			if (isset($event->key)) {
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'backend']);
				TagDependency::invalidate($cache, $event->key);
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'frontend']);
				TagDependency::invalidate($cache, $event->key);
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'console']);
				TagDependency::invalidate($cache, $event->key);
			} else {
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'backend']);
				$cache->flush();
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'frontend']);
				$cache->flush();
				$cache->cachePath = strtr($baseCachePath, ['{{APP}}' => 'console']);
				$cache->flush();
			}

			$cache->cachePath = $initialCachePath;
		});
	}

    public static function sanitize($value)
    {
        $value = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $value);
        $value = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $value);
        $value = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $value);
        $value = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $value);
        $value = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $value);
        $value = preg_replace('/[\x{1F1E6}-\x{1F1FF}]/u', '', $value);
        $value = preg_replace('/[\x{1F910}-\x{1F95E}]/u', '', $value);
        $value = preg_replace('/[\x{1F980}-\x{1F991}]/u', '', $value);
        $value = preg_replace('/[\x{1F9C0}]/u', '', $value);
        $value = preg_replace('/[\x{1F9F9}]/u', '', $value);
        $value = preg_replace('#<meta(.*?)>(.*?)</meta>#is', '', $value);
        $value = preg_replace('/<meta[^>]+\>/i', '', $value);
        $value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
        $value = preg_replace('/<script[^>]+\>/i', '', $value);
        $value = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $value);
        $value = preg_replace('/<iframe[^>]+\>/i', '', $value);
        $value = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $value);
        $value = preg_replace('/<style[^>]+\>/i', '', $value);
        return $value;
    }

    /**
     * Strips HTML tags from a given data.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function recursiveStripTags($data)
    {
        $allowableTags = [
            // basic
            // '!DOCTYPE', 'html', 'title', 'body',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr',
            // formatting
            'acronym', 'abbr', 'address', 'b', 'bdi', 'bdo', 'big',
            'blockquote', 'center', 'cite', 'code', 'del', 'dfn', 'em',
            'font', 'i', 'ins', 'kbd', 'mark', 'meter', 'pre', 'progress',
            'q', 'rp', 'rt', 'ruby', 's', 'samp', 'small', 'strike', 'strong',
            'sub', 'sup', 'time', 'tt', 'u', 'var', 'wbr',
            // forms and input
            'form', 'input', 'textarea', 'button', 'select', 'optgroup', 'option',
            'label', 'fieldset', 'legend', 'datalist', 'keygen', 'output',
            // frames
            // 'frame', 'frameset', 'noframes', 'iframe',
            // images
            'img', 'map', 'area', 'canvas', 'figcaption', 'figure',
            // audio and video
            'audio', 'source', 'track', 'video',
            // links
            'a', 'link', 'nav',
            // lists
            'ul', 'ol', 'li', 'dir', 'dl', 'dt', 'dd', 'menu', 'menuitem',
            // tables
            'table', 'caption', 'th', 'tr', 'td', 'thead', 'tbody', 'tfoot', 'col', 'colgroup',
            // styles and semantics
            'style', 'div', 'span', 'header', 'footer', 'main', 'section', 'article',
            'aside', 'details', 'dialog', 'summary',
            // meta info
            // 'head', 'meta', 'base', 'basefont',
            // programming
            // 'script', 'noscript', 'applet', 'embed', 'object', 'param',
        ];
        $allowableTags = '<' . implode('><', $allowableTags) . '>';
        $allowableAttributes = ['class', 'id', 'style', 'data-', 'aria-'];
        libxml_use_internal_errors(true);
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::recursiveStripTags($value);
                } else {
                    $data[$key] = self::sanitize($value);
                    $data[$key] = strip_tags($value, $allowableTags);
                    $document = new \DOMDocument('1.0', 'UTF-8');
                    $document->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>' . $data[$key] . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
                    // Remove the attributes
                    foreach($document->getElementsByTagName('*') as $node) {
                        for ($i = $node->attributes->length -1; $i >= 0; $i--) {
                            $attribute = $node->attributes->item($i);

                            // Handle wildcard attributes
                            $wildcardAttribute = substr($attribute->name, 0, 5);

                            if (
                                !in_array($attribute->name, $allowableAttributes) &&
                                !in_array($wildcardAttribute, $allowableAttributes)
                            ) {
                                $node->removeAttributeNode($attribute);
                            }
                        }
                    }
                    // Get the inner HTML of the container
                    $container = $document->getElementsByTagName('div')->item(0);
                    if ($container instanceof \DOMNode) {
                        $container = $container->parentNode->removeChild($container);
                        while ($document->firstChild) {
                            $document->removeChild($document->firstChild);
                        }
                        while ($container->firstChild) {
                            $document->appendChild($container->firstChild);
                        }
                    }
                    $data[$key] = trim($document->saveHTML((new \DOMXPath($document))->query('/')->item(0)));
                }
            }
        } else {
            $data = self::sanitize($data);
            $data = strip_tags($data, $allowableTags);
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>' . $data . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            // Remove the attributes
            foreach($document->getElementsByTagName('*') as $node) {
                for ($i = $node->attributes->length -1; $i >= 0; $i--) {
                    $attribute = $node->attributes->item($i);

                    // Handle wildcard attributes
                    $wildcardAttribute = substr($attribute->name, 0, 5);

                    if (
                        !in_array($attribute->name, $allowableAttributes) &&
                        !in_array($wildcardAttribute, $allowableAttributes)
                    ) {
                        $node->removeAttributeNode($attribute);
                    }
                }
            }
            // Get the inner HTML of the container
            $container = $document->getElementsByTagName('div')->item(0);
            if ($container instanceof \DOMNode) {
                $container = $container->parentNode->removeChild($container);
                while ($document->firstChild) {
                    $document->removeChild($document->firstChild);
                }
                while ($container->firstChild) {
                    $document->appendChild($container->firstChild);
                }
            }
            $data = trim($document->saveHTML((new \DOMXPath($document))->query('/')->item(0)));
        }
        return $data;
    }

	/**
	 * Gets the language from the URL with fallback to the current language.
	 *
	 * @return string
	 */
	public function getUrlLanguage()
	{
		// Check only the first two segments
		$segments = array_slice(explode('/', Yii::$app->request->pathInfo), 0, 2);

		// Ensure that the segments match the language pattern
		$segments = array_filter($segments, function ($segment) {
			return strlen($segment) === 2;
		});

		// Return the language from the URL path, or fallback to the current app language
		foreach ($segments as $segment) {
			if (array_key_exists($segment, $this->languages)) {
				return $this->languages[$segment];
			}
		}

		return Yii::$app->language;
	}

	/**
	 * Tracks the logged-in user's last activity.
	 *
	 * Skipped inside secondary applications (created by the `instance` component for
	 * cross-app URL generation): touching `user->isGuest` there re-opens the session
	 * of the primary request ("Session is already started" warning) and duplicates
	 * the identity SELECT + activity UPDATE on every page.
	 *
	 * The write itself is throttled: `last_activity` has second granularity and is
	 * only displayed, so re-writing it on every request would just add a row lock
	 * and a binlog entry per hit.
	 *
	 * @throws \Exception
	 */
	protected function updateUserActivity()
	{
		if (\tws\instance\Instance::$creatingSecondaryApp) {
			return;
		}
		if (!Yii::$app->user->isGuest) {
			/** @var \common\models\User $user */
			$user = Yii::$app->user->identity;
			$now = new \DateTime();
			if ($user->last_activity === null
				|| $now->getTimestamp() - (new \DateTime($user->last_activity))->getTimestamp() >= 60
			) {
				$user->updateAttributes(['last_activity' => $now->format('Y-m-d H:i:s')]);
			}
		}
	}

	/**
	 * Initializes Settings component.
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initSettings()
	{
		/** @var \tws\settings\Settings $settings */
		$settings = Yii::$app->has('settings') ? Yii::$app->get('settings') : new \tws\settings\Settings();
		$settings->data = (array) \common\models\Setting::findAppSettings();
		Yii::$app->set('settings', $settings);

		// Set custom app properties
		Yii::$app->name = Yii::$app->settings->get('appName');

		// Set the default application language
		if (Yii::$app->language != Yii::$app->settings->get('defaultLanguage')) {
			Yii::$app->language = Yii::$app->settings->get('defaultLanguage') ?: Yii::$app->sourceLanguage;
		}
	}

	/**
	 * Initializes Formatter component.
	 * @link http://www.yiiframework.com/doc-2.0/yii-i18n-formatter.html
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initFormatter()
	{
		if (Yii::$app->has('formatter')) {
			/** @var \yii\i18n\Formatter $formatter */
			$formatter = Yii::$app->get('formatter');
			$settings = Yii::$app->settings->getCategory('general');

			if (!empty($settings['timeZone'])) {
				Yii::$app->setTimeZone($settings['timeZone']);
			}
			$formatter->timeZone = Yii::$app->getTimeZone();
			$formatter->defaultTimeZone = Yii::$app->getTimeZone();
			$formatter->timeFormat = $settings['timeFormat'] ?: 'HH:mm:ss';
			$formatter->dateFormat = $settings['dateFormat'] ?: 'yyyy-MM-dd';
			$formatter->datetimeFormat = $settings['datetimeFormat'] ?: 'yyyy-MM-dd HH:mm:ss';
			$formatter->currencyCode = $settings['currencyCode'] ?: 'USD';
			$formatter->nullDisplay = '';

			$currencyCodesMap = [
				'RON' => 'Lei',
			];
			if (array_key_exists(mb_strtoupper($formatter->currencyCode), $currencyCodesMap)) {
				$formatter->currencyCode = $currencyCodesMap[mb_strtoupper($formatter->currencyCode)];
			}

			Yii::$app->set('formatter', $formatter);
		}
	}

	/**
	 * Initializes Mailer component.
	 * @link http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initMailer()
	{
		if (Yii::$app->has('mailer')) {
			/** @var \yii\symfonymailer\Mailer $mailer */
			$mailer = Yii::$app->get('mailer');
			$settings = Yii::$app->settings->getCategory('email');

			$mailer->messageConfig = array_merge($mailer->messageConfig, array_filter([
				'from' => filter_var($settings['from'], FILTER_VALIDATE_EMAIL) ? [$settings['from'] => Yii::$app->name] : null,
				'replyTo' => filter_var($settings['replyTo'], FILTER_VALIDATE_EMAIL) ? [$settings['replyTo'] => Yii::$app->name] : null,
			]));
			// Symfony mailer: implicit TLS (ssl/465) uses the smtps scheme; STARTTLS on
			// other ports is negotiated automatically by the smtp scheme.
			$mailer->setTransport([
				'scheme' => ($settings['encryption'] ?? '') === 'ssl' ? 'smtps' : 'smtp',
				'host' => $settings['host'],
				// The settings store the port as a string; symfony's Dsn requires ?int
				'port' => (int) $settings['port'] ?: null,
				'username' => $settings['username'],
				'password' => $settings['password'],
			]);

			Yii::$app->set('mailer', $mailer);
		}
	}

	/**
	 * Initializes Commercial components.
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initCommercial()
	{
		if (Yii::$app->has('company')) {
			/** @var \tws\commercial\components\Company $company */
			$company = Yii::$app->get('company');
			$settings = Yii::$app->settings->getCategory('commercial');

			$company->defaultGatewayKey = $settings['defaultGateway'];
			if (!empty($settings['openApiBaseUrl'])) {
				$company->addGateway('OpenApi', [
					'class' => 'tws\commercial\gateways\company\OpenApi',
					'baseUrl' => $settings['openApiBaseUrl'],
					'apiKey' => $settings['openApiKey'],
				]);
			}

			Yii::$app->set('company', $company);
		}
	}

	/**
	 * Initializes Payment component.
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function initPayment()
	{
		if (Yii::$app->has('payment')) {
			/** @var \tws\payment\Payment $payment */
			$payment = Yii::$app->get('payment');
			$settings = Yii::$app->settings->getCategory('payment');

			if (array_key_exists(\common\models\PaymentMetadata::PAYMENT_METHOD_CARD, (array) $settings['paymentMethods'])) {
				$activePaymentProcessors = (array) $settings['paymentProcessors'][\common\models\PaymentMetadata::PAYMENT_METHOD_CARD];
				if (array_key_exists(\common\models\PaymentMetadata::PAYMENT_PROCESSOR_STRIPE, $activePaymentProcessors)) {
					$payment->addProcessor(\common\models\PaymentMetadata::PAYMENT_PROCESSOR_STRIPE, [
						// App-level override: the vendor class' findTransactions() is a stub
						// that cannot verify recurring charges (see StripeProcessor).
						'class' => 'common\components\StripeProcessor',
						'baseUrl' => $settings['stripeBaseUrl'],
						'privateKey' => $settings['stripePrivateKey'],
						'publicKey' => $settings['stripePublicKey'],
					]);
				}
			}

			Yii::$app->set('payment', $payment);
		}
	}
}
