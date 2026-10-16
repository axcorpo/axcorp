<?php

namespace frontend\models;

use common\helpers\CaptchaHelper;
use borales\extensions\phoneInput\PhoneInputValidator;
use Yii;
use yii\base\Model;

class ContactForm extends Model
{
	/**
	 * @var string The contact name.
	 */
	public $name;

	/**
	 * @var string The email address.
	 */
	public $email;

	/**
	 * @var string The phone number.
	 */
	public $phone;

	/**
	 * @var string The subject of the email.
	 */
	public $subject;

	/**
	 * @var string The message of the email.
	 */
	public $message;

	/**
	 * @var string The honeypot field.
	 */
	public $workEmail;

    /**
     * @var string The honeypot field.
     */
    public $captchaResponse;


    /**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['name', 'email', 'phone', 'subject', 'message'], 'required'],
			['phone', PhoneInputValidator::class, 'skipOnEmpty' => true],
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
			'name' => Yii::t('label', 'Name'),
			'email' => Yii::t('label', 'Email'),
			'phone' => Yii::t('label', 'Phone'),
			'subject' => Yii::t('label', 'Subject'),
			'message' => Yii::t('label', 'Message'),
		];
	}

	/**
	 * Sends an email to the specified email address using the information collected by this model.
	 *
	 * @return bool whether the email was sent.
	 */
	public function sendEmail()
	{
		if (!empty($this->workEmail)) {
			return false;
		}
        if (!CaptchaHelper::verify($this->captchaResponse)) {
            $this->addError('captchaResponse', Yii::t('common', 'The captcha verification failed. Please try again.'));
            return false;
        }
		return Yii::$app->mailer
			->compose(
				['html' => 'contact-html', 'text' => 'contact-text'],
				['model' => $this]
			)
			->setTo(Yii::$app->settings->get('email', 'contact'))
			->setReplyTo([$this->email => $this->name])
			->setSubject($this->subject)
			->send();
	}
}
