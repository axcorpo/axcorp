<?php

namespace common\services;

use common\models\Affiliate;
use common\models\AffiliatePayout;
use common\models\Page;
use common\models\Template;
use common\models\User;
use tws\helpers\Url;
use Yii;
use yii\base\Event;

/**
 * E-mails to affiliates, sent from the database templates (Template::TYPE_EMAIL):
 *
 * - about their payouts (variants 109/110), when AffiliatePayoutService fires
 *   [[AffiliatePayoutService::EVENT_AFTER_PAID]] / [[AffiliatePayoutService::EVENT_INVOICE_REJECTED]];
 * - about their application (variants 111/112), when AffiliateApplicationService approves
 *   or rejects it.
 *
 * Plus the plain notice to the site's contact address that a company applied
 * (common/mail/affiliate-application-*).
 *
 * The payout handlers are class-level (Event::on on AffiliatePayout), registered once by
 * [[attachEventHandlers()]] — the backend affiliate module does it at bootstrap. A failed
 * e-mail never undoes the change it reports: it is logged and the send returns false.
 *
 * Shortcodes: {{APP_NAME}}, {{APP_URL}}, {{APP_LOGO_URL}}, {{APP_LOGO_ALT_URL}},
 * {{FIRST_NAME}}, {{LAST_NAME}}, {{ACCOUNT_URL}}; payouts add {{AMOUNT}}, {{CURRENCY}},
 * {{REASON}}; applications add {{COMPANY_NAME}}, {{AFFILIATE_CODE}}, {{REASON}}.
 */
class AffiliateNotificationService
{
	/**
	 * Frontend account routes tried, in order, for {{ACCOUNT_URL}}: the first one that has a
	 * `page` row wins (the account pages are addressed by their translated slug).
	 */
	const ACCOUNT_ROUTES = [
		'/account/affiliate-payout/index',
		'/account/affiliate/index',
		'/account/profile/index',
	];

	/**
	 * The same, for the application e-mails: an approved affiliate lands on their dashboard.
	 */
	const APPLICATION_ACCOUNT_ROUTES = [
		'/account/affiliate/index',
		'/account/profile/index',
	];

	/**
	 * @var bool whether the handlers are attached already (bootstrap may run more than once
	 * in tests)
	 */
	private static $_attached = false;

	/**
	 * Registers the payout event handlers (idempotent).
	 */
	public static function attachEventHandlers()
	{
		if (self::$_attached) {
			return;
		}
		self::$_attached = true;

		Event::on(AffiliatePayout::class, AffiliatePayoutService::EVENT_AFTER_PAID, function (Event $event) {
			(new static())->sendPayoutPaid($event->sender);
		});
		Event::on(AffiliatePayout::class, AffiliatePayoutService::EVENT_INVOICE_REJECTED, function (Event $event) {
			(new static())->sendInvoiceRejected($event->sender);
		});
	}

	/**
	 * Tells the affiliate their payout was paid.
	 *
	 * @param AffiliatePayout $payout
	 * @return bool whether the e-mail was sent
	 */
	public function sendPayoutPaid(AffiliatePayout $payout)
	{
		return $this->send($payout, Template::EMAIL_VARIANT_AFFILIATE_PAYOUT_PAID);
	}

	/**
	 * Tells the affiliate their invoice was rejected, with the reason.
	 *
	 * @param AffiliatePayout $payout
	 * @return bool whether the e-mail was sent
	 */
	public function sendInvoiceRejected(AffiliatePayout $payout)
	{
		return $this->send($payout, Template::EMAIL_VARIANT_AFFILIATE_INVOICE_REJECTED);
	}

	/**
	 * Tells the applicant their affiliate application was approved.
	 *
	 * @param Affiliate $affiliate
	 * @return bool whether the e-mail was sent
	 */
	public function sendApplicationApproved(Affiliate $affiliate)
	{
		return $this->sendApplication($affiliate, Template::EMAIL_VARIANT_AFFILIATE_APPLICATION_APPROVED);
	}

	/**
	 * Tells the applicant their affiliate application was rejected, with the reason.
	 *
	 * @param Affiliate $affiliate
	 * @return bool whether the e-mail was sent
	 */
	public function sendApplicationRejected(Affiliate $affiliate)
	{
		return $this->sendApplication($affiliate, Template::EMAIL_VARIANT_AFFILIATE_APPLICATION_REJECTED);
	}

	/**
	 * Tells the site's contact address that a company applied to become an affiliate.
	 *
	 * @param Affiliate $affiliate
	 * @return bool whether the e-mail was sent
	 */
	public function sendApplicationReceived(Affiliate $affiliate)
	{
		try {
			$to = Yii::$app->has('settings') ? Yii::$app->settings->get('email', 'contact') : null;
			if (!$to) {
				throw new \Exception('No contact e-mail address is set.');
			}
			$user = $affiliate->user;
			$company = $affiliate->company;
			$message = Yii::$app->mailer
				->compose(
					['html' => 'affiliate-application-html', 'text' => 'affiliate-application-text'],
					['affiliate' => $affiliate, 'user' => $user, 'company' => $company, 'reviewUrl' => $this->getReviewUrl($affiliate)]
				)
				->setTo($to)
				->setSubject('[' . Yii::$app->name . ']: ' . Yii::t('common', 'Affiliate application from {0}', [
					$company ? $company->name : ($user ? $user->getFullName() : ''),
				]));
			if ($user && $user->email) {
				$message->setReplyTo([$user->email => $user->getFullName()]);
			}
			return (bool) $message->send();
		} catch (\Throwable $e) {
			Yii::error(['message' => 'Affiliate application notice not sent.', 'affiliate' => $affiliate->uuid, 'error' => $e->getMessage()], __METHOD__);
			return false;
		}
	}

	/**
	 * @param AffiliatePayout $payout
	 * @param int $variant
	 * @return bool
	 */
	protected function send(AffiliatePayout $payout, $variant)
	{
		$user = $payout->affiliate ? $payout->affiliate->user : null;
		return $this->sendTemplate($user, $variant, function () use ($payout, $user) {
			return $this->getShortCodeValues($payout, $user);
		}, ['payout' => $payout->uuid]);
	}

	/**
	 * @param Affiliate $affiliate
	 * @param int $variant
	 * @return bool
	 */
	protected function sendApplication(Affiliate $affiliate, $variant)
	{
		$user = $affiliate->user;
		return $this->sendTemplate($user, $variant, function () use ($affiliate, $user) {
			return $this->getApplicationShortCodeValues($affiliate, $user);
		}, ['affiliate' => $affiliate->uuid]);
	}

	/**
	 * Sends a database e-mail template to an affiliate's user.
	 *
	 * @param User|null $user
	 * @param int $variant
	 * @param callable $values returns the shortcode => value map
	 * @param array $logContext
	 * @return bool
	 */
	protected function sendTemplate($user, $variant, callable $values, array $logContext)
	{
		try {
			if (!$user instanceof User || !$user->email) {
				throw new \Exception('The affiliate has no e-mail address.');
			}
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, $variant);
			$translation = $template ? $this->pickTranslation($template->templateTranslations) : null;
			if (!$translation) {
				throw new \Exception("No e-mail template for variant {$variant}.");
			}

			$values = $values();
			return (bool) Yii::$app->mailer->compose()
				->setTo([$user->email => $user->getFullName()])
				->setSubject(strtr((string) $translation->subject, $values))
				->setHtmlBody(strtr((string) $translation->content, $values))
				->send();
		} catch (\Throwable $e) {
			Yii::error(array_merge(['message' => 'Affiliate e-mail not sent.', 'variant' => $variant, 'error' => $e->getMessage()], $logContext), __METHOD__);
			return false;
		}
	}

	/**
	 * @param AffiliatePayout $payout
	 * @param User $user
	 * @return array shortcode => value; values typed by people are HTML-encoded, the
	 * template body is HTML
	 */
	public function getShortCodeValues(AffiliatePayout $payout, User $user)
	{
		$appUrl = $this->getAppUrl();

		return array_merge($this->getCommonShortCodeValues($appUrl, $user), [
			'{{AMOUNT}}' => number_format((float) $payout->amount, 2, '.', ''),
			'{{CURRENCY}}' => htmlspecialchars((string) $payout->currency, ENT_QUOTES, 'UTF-8'),
			'{{REASON}}' => nl2br(htmlspecialchars((string) $payout->invoice_rejection_note, ENT_QUOTES, 'UTF-8')),
			'{{ACCOUNT_URL}}' => $this->getAccountUrl($appUrl),
		]);
	}

	/**
	 * @param Affiliate $affiliate
	 * @param User $user
	 * @return array shortcode => value; values typed by people are HTML-encoded, the
	 * template body is HTML
	 */
	public function getApplicationShortCodeValues(Affiliate $affiliate, User $user)
	{
		$appUrl = $this->getAppUrl();
		$company = $affiliate->company;

		return array_merge($this->getCommonShortCodeValues($appUrl, $user), [
			'{{COMPANY_NAME}}' => htmlspecialchars((string) ($company ? $company->name : ''), ENT_QUOTES, 'UTF-8'),
			'{{AFFILIATE_CODE}}' => htmlspecialchars((string) $affiliate->code, ENT_QUOTES, 'UTF-8'),
			'{{REASON}}' => nl2br(htmlspecialchars((string) $affiliate->rejection_reason, ENT_QUOTES, 'UTF-8')),
			'{{ACCOUNT_URL}}' => $this->getAccountUrl($appUrl, static::APPLICATION_ACCOUNT_ROUTES),
		]);
	}

	/**
	 * The shortcodes every affiliate e-mail shares.
	 *
	 * @param string $appUrl
	 * @param User $user
	 * @return array
	 */
	protected function getCommonShortCodeValues($appUrl, User $user)
	{
		$logo = $this->getSetting('appLogo');
		$logoAlt = $this->getSetting('appLogoAlt');

		return [
			'{{APP_NAME}}' => Yii::$app->name,
			'{{APP_URL}}' => $appUrl,
			'{{APP_LOGO_URL}}' => $logo ? Url::to('@uploads/' . $logo, true) : '',
			'{{APP_LOGO_ALT_URL}}' => $logoAlt ? Url::to('@uploads/' . $logoAlt, true) : '',
			'{{FIRST_NAME}}' => htmlspecialchars((string) $user->first_name, ENT_QUOTES, 'UTF-8'),
			'{{LAST_NAME}}' => htmlspecialchars((string) $user->last_name, ENT_QUOTES, 'UTF-8'),
		];
	}

	/**
	 * The affiliate's account page on the master frontend.
	 *
	 * @param string $appUrl
	 * @param string[]|null $routes tried in order; default [[ACCOUNT_ROUTES]]
	 * @return string
	 */
	protected function getAccountUrl($appUrl, $routes = null)
	{
		try {
			$language = mb_substr(Yii::$app->language, 0, 2);
			foreach ($routes ?: static::ACCOUNT_ROUTES as $route) {
				$page = Page::findPageByRoute([$route]);
				$translation = $page ? $this->pickTranslation($page->pageTranslations) : null;
				if ($translation && $translation->slug) {
					return implode('/', array_filter([rtrim($appUrl, '/'), $language, $translation->slug]));
				}
			}
		} catch (\Throwable $e) {
			Yii::warning(['message' => 'Affiliate account URL not resolved.', 'error' => $e->getMessage()], __METHOD__);
		}
		return $appUrl;
	}

	/**
	 * The admin page of an application, for the notice to the site; null where the backend
	 * URL manager is not registered (console).
	 *
	 * @param Affiliate $affiliate
	 * @return string|null
	 */
	protected function getReviewUrl(Affiliate $affiliate)
	{
		try {
			return Url::to(['/affiliate-manager/affiliate/view', 'id' => $affiliate->uuid], true, '@backend');
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * The frontend's absolute base URL; the admin shares its host (/admin), so the current
	 * request's host is the frontend's.
	 *
	 * @return string
	 */
	protected function getAppUrl()
	{
		if (Yii::$app->request instanceof \yii\web\Request) {
			return Yii::$app->request->hostInfo;
		}
		return (string) (Yii::$app->params['frontendUrl'] ?? '');
	}

	/**
	 * The translation in the current language, else the default language, else none.
	 * (The models' getTranslation() indexes the language without checking it exists.)
	 *
	 * @param \yii\db\ActiveRecord[] $translations rows with a `language_id`
	 * @return \yii\db\ActiveRecord|null
	 */
	protected function pickTranslation($translations)
	{
		$byLanguage = [];
		foreach ((array) $translations as $translation) {
			$byLanguage[$translation->language_id] = $translation;
		}
		return $byLanguage[Yii::$app->language] ?? $byLanguage[(string) $this->getSetting('defaultLanguage')] ?? null;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	protected function getSetting($key)
	{
		return Yii::$app->has('settings') ? Yii::$app->settings->get($key) : null;
	}
}
