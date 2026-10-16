<?php

namespace common\traits;

use common\models\Page;
use common\models\Template;
use common\models\User;
use tws\helpers\Url;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * The welcome and activation e-mails of an account created on someone's behalf, sent from
 * the database templates (Template::EMAIL_VARIANT_WELCOME / EMAIL_VARIANT_ACCOUNT_ACTIVATION).
 *
 * Shared by the forms that create affiliate accounts: the admin's AffiliateForm and the
 * public affiliate application (frontend\models\AffiliateApplicationForm).
 */
trait AccountEmailTrait
{
	/**
	 * The shortcodes every account e-mail shares.
	 *
	 * @param User $user
	 * @return array
	 */
	protected function getAccountEmailShortCodeValues(User $user)
	{
		return [
			'{{APP_NAME}}' => Yii::$app->name,
			'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
			'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
			'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
			'{{FIRST_NAME}}' => $user->first_name,
			'{{MIDDLE_NAME}}' => $user->middle_name,
			'{{LAST_NAME}}' => $user->last_name,
			'{{EMAIL}}' => $user->email,
			'{{PHONE}}' => $user->phone,
		];
	}

	/**
	 * Sends the welcome e-mail (the same template SubscriberUserForm uses).
	 *
	 * @param User $user
	 * @return bool
	 */
	public function sendWelcomeEmail(User $user)
	{
		try {
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_WELCOME);
			if (!$template || !($templateTranslation = static::pickTranslation($template->templateTranslations))) {
				throw new \Exception();
			}
			$shortCodeValues = $this->getAccountEmailShortCodeValues($user);

			return Yii::$app->mailer->compose()
				->setTo([$user->email => $user->fullName])
				->setSubject(strtr($templateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($templateTranslation->content, $shortCodeValues))
				->send();
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Sends the account activation e-mail (as SubscriberUserForm::sendActivationEmail()).
	 *
	 * @param User $user
	 * @return bool
	 */
	public function sendActivationEmail(User $user)
	{
		try {
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_ACCOUNT_ACTIVATION);
			$page = Page::findPageByRoute(['/site/activate']);
			$templateTranslation = $template ? static::pickTranslation($template->templateTranslations) : null;
			$pageTranslation = $page ? static::pickTranslation($page->pageTranslations) : null;
			if (!$templateTranslation || !$pageTranslation) {
				return false;
			}
			$language = mb_substr($pageTranslation->language_id, 0, 2);
			$activationPageUrl = implode('/', array_filter([
				Yii::$app->request->hostInfo,
				$language,
				$pageTranslation->slug,
			]));
			$shortCodeValues = array_merge($this->getAccountEmailShortCodeValues($user), [
				'{{ACCOUNT_ACTIVATION_CODE}}' => $user->getSignupToken(),
				'{{ACCOUNT_ACTIVATION_PAGE_URL}}' => $activationPageUrl,
				'{{ACCOUNT_ACTIVATION_URL}}' => $activationPageUrl . '?token=' . $user->getSignupToken(),
			]);

			return Yii::$app->mailer->compose()
				->setTo([$user->email => $user->fullName])
				->setSubject(strtr($templateTranslation->subject, $shortCodeValues))
				->setHtmlBody(strtr($templateTranslation->content, $shortCodeValues))
				->send();
		} catch (\Exception $e) {
			Yii::error(['message' => 'Affiliate activation e-mail not sent.', 'error' => $e->getMessage()], __METHOD__);
			return false;
		}
	}

	/**
	 * The translation in the application language, else in the default language.
	 * (The models' getTranslation() indexes the language without checking it exists.)
	 *
	 * @param \yii\db\ActiveRecord[] $translations
	 * @return \yii\db\ActiveRecord|null
	 */
	protected static function pickTranslation($translations)
	{
		$byLanguage = ArrayHelper::index((array) $translations, 'language_id');
		return $byLanguage[Yii::$app->language] ?? $byLanguage[(string) Yii::$app->settings->get('defaultLanguage')] ?? null;
	}
}
