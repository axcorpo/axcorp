<?php

namespace backend\modules\setting\models;

use common\models\Setting;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;

class SeoSettingForm extends Setting
{
	/**
	 * @var string The Google Analytics code.
	 */
	public $googleAnalytics;

	/**
	 * @var string The Google verification code.
	 */
	public $googleVerification;

	/**
	 * @var string The Bing verification code.
	 */
	public $bingVerification;

	/**
	 * @var string The Pinterest verification code.
	 */
	public $pinterestVerification;

	/**
	 * @var bool Flag that indicates if the robots are allowed to crawl the public pages.
	 */
	public $allowRobotsCrawling;

	/**
	 * @var string The content of the robots.txt file.
	 */
	public $robotsFileContent;

	/**
	 * @var array The default content of the robots.txt file.
	 */
	public $_defaultRobotsFileContent;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->allowRobotsCrawling = 1;
		$this->_defaultRobotsFileContent = [
			'User-agent: *',
			'Crawl-delay: 5',
			'User-agent: Googlebot',
			'Crawl-delay: 1',
			'User-agent: Bingbot',
			'Crawl-delay: 2',
			'Disallow: /' . trim(Yii::getAlias('@web'), '/') . '/',
			'Sitemap: ' . trim(Url::to('/', true), '/') . '/sitemap',
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['allowRobotsCrawling'], 'boolean'],
			[['googleAnalytics', 'googleVerification', 'bingVerification', 'pinterestVerification', 'robotsFileContent'], 'string'],
			[['googleAnalytics', 'googleVerification', 'bingVerification', 'pinterestVerification', 'robotsFileContent'], 'trim'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'googleAnalytics' => Yii::t('label', 'Google Analytics Code'),
			'googleVerification' => Yii::t('label', 'Google Verification Code'),
			'bingVerification' => Yii::t('label', 'Bing Verification Code'),
			'pinterestVerification' => Yii::t('label', 'Pinterest Verification Code'),
			'allowRobotsCrawling' => Yii::t('label', 'Allow Robots Crawling'),
			'robotsFileContent' => Yii::t('label', 'Robots File Content'),
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

		if (!$this->allowRobotsCrawling) {
			$this->robotsFileContent = implode("\n", $this->_defaultRobotsFileContent);
		}
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$robotsFileContent = array_replace(
			explode("\n", $this->robotsFileContent),
			$this->_defaultRobotsFileContent
		);
		if (!$this->allowRobotsCrawling) {
			$robotsFileContent = [
				'User-agent: *',
				'Disallow: /',
			];
		}
		$this->robotsFileContent = implode("\n", $robotsFileContent);

		$this->name = 'seo';
		$this->type = static::TYPE_APP;
		$this->status = static::STATUS_ACTIVE;
		$this->setSerializedValue('setting', [
			'googleAnalytics' => $this->googleAnalytics,
			'googleVerification' => $this->googleVerification,
			'bingVerification' => $this->bingVerification,
			'pinterestVerification' => $this->pinterestVerification,
			'allowRobotsCrawling' => $this->allowRobotsCrawling,
			'robotsFileContent' => $this->robotsFileContent,
		]);

		if ($this->save()) {
			file_put_contents(Yii::getAlias('@frontend/web/robots.txt'), $this->robotsFileContent);
			return $this;
		}
		return null;
	}
}
