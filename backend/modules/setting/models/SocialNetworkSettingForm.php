<?php

namespace backend\modules\setting\models;

use common\models\Setting;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class SocialNetworkSettingForm extends Setting
{
	/**
	 * @var string Facebook Application ID.
	 */
	public $facebookAppId;

	/**
	 * @var bool Flag that indicates if the Facebook customer chat should be enabled.
	 */
	public $enableFacebookCustomerChat;

	/**
	 * @var string Facebook page URL.
	 */
	public $facebookPage;

	/**
	 * @var string Instagram page URL.
	 */
	public $instagramPage;

	/**
	 * @var string LinkedIn page URL.
	 */
	public $linkedinPage;

	/**
	 * @var string Youtube Channel URL.
	 */
	public $youtubeChannel;

	/**
	 * @var string Pinterest page URL.
	 */
	public $pinterestPage;

	/**
	 * @var string TikTok page URL.
	 */
	public $tiktokPage;

	/**
	 * @var string Twitter page URL.
	 */
	public $twitterPage;

	/**
	 * @var string Twitter Site.
	 */
	public $twitterSite;

	/**
	 * @var string Twitter Creator.
	 */
	public $twitterCreator;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['facebookAppId', 'facebookPage', 'instagramPage', 'linkedinPage', 'youtubeChannel', 'pinterestPage', 'tiktokPage', 'twitterPage', 'twitterSite', 'twitterCreator'], 'string'],
			[['facebookAppId', 'facebookPage', 'instagramPage', 'linkedinPage', 'youtubeChannel', 'pinterestPage', 'tiktokPage', 'twitterPage', 'twitterSite', 'twitterCreator'], 'trim'],
			[['facebookPage', 'instagramPage', 'linkedinPage', 'youtubeChannel', 'pinterestPage', 'tiktokPage', 'twitterPage'], 'url'],
			[['enableFacebookCustomerChat'], 'boolean'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'facebookAppId' => Yii::t('label', 'Facebook Application ID'),
			'enableFacebookCustomerChat' => Yii::t('label', 'Enable Facebook Customer Chat'),
			'facebookPage' => Yii::t('label', 'Facebook Page'),
			'instagramPage' => Yii::t('label', 'Instagram Page'),
			'linkedinPage' => Yii::t('label', 'Linkedin Page'),
			'youtubeChannel' => Yii::t('label', 'Youtube Channel'),
			'pinterestPage' => Yii::t('label', 'Pinterest Page'),
			'tiktokPage' => Yii::t('label', 'TikTok Page'),
			'twitterPage' => Yii::t('label', 'Twitter Page'),
			'twitterSite' => Yii::t('label', 'Twitter Site'),
			'twitterCreator' => Yii::t('label', 'Twitter Creator'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->setAttributes($this->getUnserializedValue('setting'));
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$this->name = 'socialNetwork';
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			'facebookAppId' => $this->facebookAppId,
			'enableFacebookCustomerChat' => $this->enableFacebookCustomerChat,
			'facebookPage' => $this->facebookPage,
			'instagramPage' => $this->instagramPage,
			'linkedinPage' => $this->linkedinPage,
			'youtubeChannel' => $this->youtubeChannel,
			'pinterestPage' => $this->pinterestPage,
			'tiktokPage' => $this->tiktokPage,
			'twitterPage' => $this->twitterPage,
			'twitterSite' => $this->twitterSite,
			'twitterCreator' => $this->twitterCreator,
		]);

		return $this->save() ? $this : null;
	}
}
