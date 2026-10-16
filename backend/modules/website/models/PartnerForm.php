<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\models\Language;
use common\models\Partner;
use common\models\PartnerTranslation;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use common\helpers\StringHelper;
use yii\web\UploadedFile;

class PartnerForm extends Partner
{
	/**
	 * @var int The image file.
	 */
	public $imageFile;

	/**
	 * @var array The multilingual description of the partner.
	 */
	public $description = [];

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
			[['name'], 'required'],
			[['description'], 'each', 'rule' => ['trim']],
			[['url', 'icon'], 'trim'],
			[['url', 'icon'], 'default', 'value' => null],
			[['url'], 'url'],
			[['imageFile'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
            [['translator'], 'safe'],
        ]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'imageFile' => Yii::t('label', 'Image'),
			'description' => Yii::t('label', 'Description'),
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

		$this->description = ArrayHelper::map($this->partnerTranslations, 'language_id', 'description');
	}

	/**
	 * Saves the files.
	 *
	 * @return bool
	 */
	protected function saveFiles()
	{
		try {
			if (!($file = UploadedFile::getInstance($this, 'imageFile'))) {
				return true;
			}

			$dirPath = Yii::getAlias("@uploads/partner/{$this->uuid}");
			$oldFilePath = "{$dirPath}/{$this->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($this->name),
				Yii::$app->security->generateRandomString(8),
			])), 255 - (mb_strlen($file->extension) + 1), '') . ".{$file->extension}";
			$filePath = "{$dirPath}/{$fileName}";

			FileHelper::createDirectory($dirPath);
			if (!$file->saveAs($filePath)) {
				throw new \Exception();
			}
			if (!$this->updateAttributes(['image' => $fileName])) {
				throw new \Exception();
			}
			if (is_file($oldFilePath) && $oldFilePath != $filePath) {
				FileHelper::unlink($oldFilePath);
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function savePartnerTranslations()
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
                $partnerTranslation = PartnerTranslation::findOne([
                    'partner_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$partnerTranslation) {
                    $partnerTranslation = new PartnerTranslation();
                    $partnerTranslation->partner_id = $this->id;
                    $partnerTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $parts = StringHelper::splitString($this->description[$defaultLanguage->language_id], 5000);
                $translations = [];
                foreach ($parts as $part) {
                    $translation = html_entity_decode($part);
                    $translation = $source && $target ? Yii::$app->translate->translate($source, $target, $translation)['data']['translations'][0]['translatedText'] : null;
                    $translation = html_entity_decode($translation);
                    $translation = ApplicationBootstrap::recursiveStripTags($translation);
                    $translation = str_replace(['& nbsp;', '&nbsp;', '< / p>'], [' ', ' ', ''], $translation);
                    $translations[] = $translation;
                }
                $partnerTranslation->description = $this->description[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->description[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($partnerTranslation->description) {
                    $this->link('partnerTranslations', $partnerTranslation);
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
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			unset($this->image);
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
				throw new \Exception();
			}
			if (!$this->savePartnerTranslations()) {
				throw new \Exception();
			}
			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
