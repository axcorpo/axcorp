<?php

namespace backend\modules\helpdesk\models;

use common\components\ApplicationBootstrap;
use common\helpers\StringHelper;
use common\models\Language;
use common\models\SupportTicketPriority;
use common\models\SupportTicketPriorityTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class SupportTicketPriorityForm extends SupportTicketPriority
{
	/**
	 * @var array The multilingual name of the SupportTicketPriority.
	 */
	public $name;

	/**
	 * @var array The multilingual content of the SupportTicketPriority.
	 */
	public $content;

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

		$this->status = static::STATUS_ACTIVE;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['name'], 'required', 'when' => function ($model) {
				return empty($model->name[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
        return attribute.$form.find("[name*=\"[name][' . Yii::$app->language . ']\"]").val() == "";
    	}'],
			[['name', 'content'], 'each', 'rule' => ['trim']],
			[['content'], 'each', 'rule' => ['default', 'value' => null]],
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

		$this->name = ArrayHelper::map($this->supportTicketPriorityTranslations, 'language_id', 'name');
		$this->content = ArrayHelper::map($this->supportTicketPriorityTranslations, 'language_id', 'content');
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveSupportTicketPriorityTranslations()
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

            $source = $target = null;

            foreach (Language::findAllLanguages() as $language) {
                $supportTicketPriorityTranslation = SupportTicketPriorityTranslation::findOne([
                    'support_ticket_priority_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$supportTicketPriorityTranslation) {
                    $supportTicketPriorityTranslation = new SupportTicketPriorityTranslation();
                    $supportTicketPriorityTranslation->support_ticket_priority_id = $this->id;
                    $supportTicketPriorityTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }

                $supportTicketPriorityTranslation->name = StringHelper::truncate($this->name[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->name[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->name[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                $parts = StringHelper::splitString($this->content[$defaultLanguage->language_id], 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translations[] = $translation;
                }
                $supportTicketPriorityTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($supportTicketPriorityTranslation->name) {
                    $this->link('supportTicketPriorityTranslations', $supportTicketPriorityTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->addError('', $e->getMessage());
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
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveSupportTicketPriorityTranslations()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
