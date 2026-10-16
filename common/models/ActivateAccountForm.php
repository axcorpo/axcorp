<?php

namespace common\models;

use common\helpers\CaptchaHelper;
use Yii;
use yii\base\Model;
use yii\helpers\Html;
use tws\helpers\Url;

class ActivateAccountForm extends Model
{
	/**
	 * @var string The account activation token.
	 */
	public $token;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

    /**
     * @var string The honeypot field.
     */
    public $captchaResponse;

    /**
     * @var bool Whether [[activate()]] verifies the captcha. False for the callers that
     * never present one: the password-reset flow and the REST API.
     */
    public $requireCaptcha = true;

	/**
	 * @var User The User model.
	 */
	private $_user;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['token'], 'required'],
			[['token'], 'trim'],
			['token', 'validateToken'],
			['workEmail', 'safe'],
            ['captchaResponse', 'safe'],
        ];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'token' => Yii::t('label', 'Activation Code'),
		];
	}

	/**
	 * Validates the token attribute.
	 *
	 * @return bool
	 */
	public function validateToken()
	{
		if (!$this->getUser()) {
			$this->addError('token', Yii::t('yii', '{attribute} is invalid.', [
				'attribute' => $this->getAttributeLabel('token'),
			]));
			return false;
		}
		return true;
	}

	/**
	 * Finds user by signup token.
	 *
	 * @return User|null
	 */
	public function getUser()
	{
		if (!$this->_user) {
			$this->_user = User::findBySignupToken($this->token);
		}
		return $this->_user;
	}

	/**
	 * Activates Subscriber model.
	 *
	 * @return bool
	 */
	public function activateSubscriber()
	{
		try {
			/** @var Subscriber $subscriber */
			if ($subscriber = $this->getUser()->getSubscriber()->active(false)->one()) {
				$subscriber->status = Subscriber::STATUS_ACTIVE;
				if (!$subscriber->save()) {
					throw new \Exception('Cannot activate the Subscriber.');
				}
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Activates the only Subscription model chosen at signup.
	 *
	 * @return bool
	 */
	public function activateSubscription()
	{
		try {
			if ($subscriber = $this->getUser()->subscriber) {
				/** @var Subscription $subscription */
				if ($subscription = $subscriber->getSubscriptions()->one()) {
					if (!$subscription->activate('now', true)) {
						throw new \Exception('Cannot activate the Subscription.');
					}
				}
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Activates the account in all workspaces.
	 *
	 * @return bool
	 */
	public function activateWorkspaceAccount()
	{
		try {
			$user = $this->getUser();

			// Perform workspace account activation only for new users
//			if (!empty($user->last_activity)) {
//				return true;
//			}

			/** @var WorkspaceHasUser[] $workspaceHasUsers */
			$workspaceHasUsers = WorkspaceHasUser::find()
				->andWhere([
					'AND',
					['=', 'user_id', $user->id],
					['IS NOT', 'signup_token', null],
				])
				->andWhere(['LIKE', 'signup_token', "{$this->token}%", false])
				->all();

			foreach ($workspaceHasUsers as $workspaceHasUser) {
				$workspaceHasUser->updateAttributes([
					'signup_token' => $user->signup_token,
					'status' => $user->status,
				]);

				// Activate user account in workspace database
				$workspaceHasUser->workspace->getWorkspaceDb()->createCommand()->update(User::tableName(),
					[
						'signup_token' => $user->signup_token,
						'status' => $user->status,
					],
					[
						'AND',
						['=', 'id', $user->id],
						['IS NOT', 'signup_token', null],
					]
				)->execute();
			}

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Sends welcome email.
	 *
	 * @return bool
	 */
	public function sendWelcomeEmail()
	{
		try {
			$template = Template::findDefaultByTypeAndVariant(Template::TYPE_EMAIL, Template::EMAIL_VARIANT_WELCOME);
			if (!$template || !($templateTranslation = $template->getTranslation())) {
				throw new \Exception();
			}
			$user = $this->getUser();
			$shortCodeValues = [
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
	 * Activates user account.
	 *
	 * @return bool if account was activated.
	 */
	public function activate()
	{
		if (!empty($this->workEmail)) {
			return false;
		}
		// Only the public activation page carries a captcha widget. The password-reset
		// flow activates a pending account on the user's behalf, and the API has no
		// captcha at all, so both switch this off rather than fail a check they could
		// never pass.
		if ($this->requireCaptcha && !CaptchaHelper::verify($this->captchaResponse)) {
			$this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
			return false;
		}
		try {
			if (!($user = $this->getUser())) {
				throw new \Exception();
			}
			$user->signup_token = null;
			$user->status = User::STATUS_ACTIVE;
			if (!$user->save(false)) {
				throw new \Exception();
			}
			if (!$this->activateSubscriber()) {
				throw new \Exception();
			}
			if (!$this->activateSubscription()) {
				throw new \Exception();
			}
			if (!$this->activateWorkspaceAccount()) {
				throw new \Exception();
			}
//			$this->sendWelcomeEmail();

			if ($marketingRecipient = $user->marketingRecipient) {
				MarketingCampaign::addFollowupRecipients([
					'recipients' => $marketingRecipient->id,
				], [
					'event' => MarketingCampaign::MKTEVENT_AFTER_ACCOUNT_ACTIVATION,
				]);
			}
			Notification::create([
				'title' => Yii::t('notification', 'Account activation'),
				'message' => Yii::t('notification', '{0} became an active member.', [
					Html::a($user->fullName, ['/admin/subscriber-manager/subscriber/view', 'id' => $user->subscriber->id]),
				]),
				'icon' => 'fa fa-user-plus',
				'color' => '#00ff00',
			]);

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}
