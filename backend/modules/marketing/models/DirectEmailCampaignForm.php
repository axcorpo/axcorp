<?php

namespace backend\modules\marketing\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\MarketingCampaignHasRecipient;
use common\models\MarketingCampaignTranslation;
use common\models\MarketingRecipient;
use common\models\ScheduledTask;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class DirectEmailCampaignForm extends DirectMarketingCampaign
{
	/**
	 * @var array The multilingual name of the MarketingCampaign.
	 */
	public $name = [];

	/**
	 * @var array The multilingual subject of the MarketingCampaign.
	 */
	public $subject = [];

	/**
	 * @var array The multilingual content of the MarketingCampaign.
	 */
	public $content = [];

	/**
	 * @var array The filters to be added as serialized data.
	 */
	public $filters = [];

	/**
	 * @var array The targeted MarketingRecipient models IDs.
	 */
	public $targetedRecipients = [];

    /**
     * @var array Enable google translator.
     */
    public $translator = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->type = static::TYPE_DIRECT;
		$this->variant = static::VARIANT_EMAIL;
		$this->frequency = 20;
		$this->cycle = ScheduledTask::CYCLE_MINUTE;
		$this->status = static::STATUS_INACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['frequency'], 'required'],
			['frequency', 'integer', 'min' => 1],
			['frequency', 'default', 'value' => 20],
			['cycle', 'default', 'value' => ScheduledTask::CYCLE_MINUTE],
			[['name'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
            return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
            }'],
			[['subject'], 'required', 'when' => function ($model) {
				return empty($model->subject[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
            return attribute.$form.find("[name*=\"[subject][' . Yii::$app->language . ']\"]").val() == "";
            }'],
			[['content'], 'required', 'when' => function ($model) {
				return empty($model->content[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
            return attribute.$form.find("[name*=\"[content][' . Yii::$app->language . ']\"]").val() == "";
            }'],
			[['name', 'subject', 'content'], 'each', 'rule' => ['trim']],
			[['targetedRecipients'], 'each', 'rule' => ['exist', 'skipOnError' => true, 'targetClass' => MarketingRecipient::class, 'targetAttribute' => ['targetedRecipients' => 'id']]],
            [['translator'], 'safe'],
        ]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'name' => Yii::t('label', 'Name'),
			'subject' => Yii::t('label', 'Subject'),
			'content' => Yii::t('label', 'Content'),
            'translator' => Yii::t('label', 'Translator'),
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

		$this->name = ArrayHelper::map($this->marketingCampaignTranslations, 'language_id', 'name');
		$this->subject = ArrayHelper::map($this->marketingCampaignTranslations, 'language_id', 'subject');
		$this->content = ArrayHelper::map($this->marketingCampaignTranslations, 'language_id', 'content');
		$this->filters = (array) $this->getUnserializedValue('data')['filters'];
		$this->targetedRecipients = ArrayHelper::getColumn($this->marketingCampaignHasRecipients, 'marketing_recipient_id');
	}


    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveMarketingCampaignTranslations()
    {
        try {
            $languages = [];
            if (!empty($this->translator)) {
            	try {
            		$languages = ArrayHelper::getColumn(Yii::$app->translate->discover()['data']['languages'], 'language');
            	} catch (\Throwable $e) {
            		Yii::error($e, __METHOD__);
            	}
            }
            $defaultLanguage = Language::findOne(['language_id' => Yii::$app->language]);

            $shortCodes = []; $reverseShortCodes = [];
            foreach (array_keys(\common\models\MarketingRecipient::getShortCodeItems()) as $key) {
                $shortCodes[$key] = md5($key);
            }
            $reverseShortCodes = array_flip($shortCodes);

            $source = $target = null;

            foreach (Language::findAllLanguages() as $language) {
                $marketingCampaignTranslation = MarketingCampaignTranslation::findOne([
                    'marketing_campaign_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$marketingCampaignTranslation) {
                    $marketingCampaignTranslation = new MarketingCampaignTranslation();
                    $marketingCampaignTranslation->marketing_campaign_id = $this->id;
                    $marketingCampaignTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $marketingCampaignTranslation->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                $translation = StringHelper::truncate($this->subject[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->subject[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, strtr($this->subject[Yii::$app->language], $shortCodes))['data']['translations'][0]['translatedText'] : null), 255, '');
                $marketingCampaignTranslation->subject = strtr($translation, $reverseShortCodes);
                $parts = StringHelper::splitString(strtr($this->content[$defaultLanguage->language_id], $shortCodes), 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translation = strtr($translation, $reverseShortCodes);
                    $translations[] = $translation;
                }
                $marketingCampaignTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($marketingCampaignTranslation->name) {
                    $this->link('marketingCampaignTranslations', $marketingCampaignTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
	 * Saves the MarketingRecipient models.
	 *
	 * @return bool
	 */
	protected function saveMarketingRecipients()
	{
		try {
			// Target only the selected MarketingRecipient models
			$existingMarketingRecipients = ArrayHelper::getColumn($this->marketingCampaignHasRecipients, 'marketing_recipient_id');

			// Delete all MarketingRecipient models that are not selected
			MarketingCampaignHasRecipient::deleteAll([
				'AND',
				['=', 'marketing_campaign_id', $this->id],
				['NOT IN', 'marketing_recipient_id', $this->targetedRecipients],
			]);

			// Get only the selected and not already existing MarketingRecipient models
			/** @var MarketingRecipient[] $marketingRecipients */
			$marketingRecipients = MarketingRecipient::find()
				->andWhere([
					'id' => $this->targetedRecipients,
					'status' => MarketingRecipient::STATUS_ACTIVE,
					'deleted' => MarketingRecipient::NO,
				])
				->andFilterWhere(['NOT IN', 'id', $existingMarketingRecipients])
				->all();

			foreach ($marketingRecipients as $marketingRecipient) {
				$marketingCampaignHasRecipient = new MarketingCampaignHasRecipient();
				$marketingCampaignHasRecipient->marketing_campaign_id = $this->id;
				$marketingCampaignHasRecipient->marketing_recipient_id = $marketingRecipient->id;
				$marketingCampaignHasRecipient->save();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			$oldAttributes = $this->getOldAttributes();

			$this->setSerializedValue('data', [
				'filters' => $this->filters,
			]);
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveMarketingCampaignTranslations()) {
				throw new \Exception();
			}
			if (!$this->saveMarketingRecipients()) {
				throw new \Exception();
			}
			if (!$this->saveScheduledTask()) {
				throw new \Exception();
			}

			switch ($this->status) {
				case static::STATUS_INACTIVE:
					$this->stopCampaign();
					break;
				case static::STATUS_ACTIVE:
					if ($oldAttributes['status'] == static::STATUS_COMPLETE) {
						$this->restartCampaign();
					} else {
						$this->startCampaign();
					}
					break;
				case static::STATUS_PAUSED:
					$this->pauseCampaign();
					break;
				case static::STATUS_COMPLETE:
					$this->completeCampaign();
					break;
				default: break;
			}

			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
