<?php

namespace frontend\models;

use common\helpers\CaptchaHelper;
use common\models\MarketingRecipient;
use Yii;
use yii\base\Model;

class NewsletterForm extends Model
{
	/**
	 * @var string The email address.
	 */
	public $email;

	/**
	 * @var string The phone number.
	 */
	public $phone;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

	/**
	 * @var string The reCAPTCHA token, produced in the browser on submit.
	 */
	public $captchaResponse;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['email', 'phone'], 'required'],
			['email', 'email'],
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
			'phone' => Yii::t('label', 'Phone'),
			'email' => Yii::t('label', 'Email'),
		];
	}

	/**
	 * Saves the newsletter subscriber.
	 *
	 * @return bool
	 */
	public function model()
	{
		if (!empty($this->workEmail)) {
			return false;
		}
		// Fails closed: once reCAPTCHA is configured, a submission without a valid token
		// (a scripted POST that never ran the page's JavaScript) is rejected.
		if (!CaptchaHelper::verify($this->captchaResponse)) {
			$this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
			return false;
		}
		try {
			// Already subscribed: report success without a second row, so repeated
			// submissions do not pile up duplicates.
			if (MarketingRecipient::find()->where(['email' => $this->email, 'deleted' => MarketingRecipient::NO])->exists()) {
				return true;
			}
			$newsletterModel = new MarketingRecipient();
			$newsletterModel->email = $this->email;
			$newsletterModel->phone = $this->phone;
			$newsletterModel->status = MarketingRecipient::STATUS_ACTIVE;
			$newsletterModel->deleted = MarketingRecipient::NO;
			if (!$newsletterModel->save()) {
				throw new \Exception();
			}
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}
}
