<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\models\Language;
use common\models\CarouselItem;
use common\models\CarouselItemTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use common\helpers\StringHelper;
use yii\web\UploadedFile;

class CarouselItemForm extends CarouselItem
{
	/**
	 * @var UploadedFile The carousel image file.
	 */
	public $imageFile;

	/**
	 * @var array The multilingual title of the carousel item.
	 */
	public $title = [];

	/**
	 * @var array The multilingual content of the carousel item.
	 */
	public $content = [];

	/**
	 * @var array The multilingual anchor of the carousel item.
	 */
	public $anchor = [];

	/**
	 * @var array The multilingual url of the carousel item.
	 */
	public $url = [];

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
			[['title', 'content', 'anchor', 'url'], 'each', 'rule' => ['trim']],
			[['title', 'content', 'anchor', 'url'], 'each', 'rule' => ['default', 'value' => null]],
			[['target'], 'default', 'value' => null],
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
			'title' => Yii::t('label', 'Title'),
			'content' => Yii::t('label', 'Content'),
			'anchor' => Yii::t('label', 'Text'),
			'url' => Yii::t('label', 'URL'),
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

		$this->title = ArrayHelper::map($this->carouselItemTranslations, 'language_id', 'title');
		$this->content = ArrayHelper::map($this->carouselItemTranslations, 'language_id', 'content');
		$this->anchor = ArrayHelper::map($this->carouselItemTranslations, 'language_id', 'anchor');
		$this->url = ArrayHelper::map($this->carouselItemTranslations, 'language_id', 'url');
	}

	/**
	 * Saves the translations.
	 *
	 * @return bool
	 */
	protected function saveCarouselItemTranslations()
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
                $carouselItemTranslation = CarouselItemTranslation::findOne([
                    'carousel_item_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$carouselItemTranslation) {
                    $carouselItemTranslation = new CarouselItemTranslation();
                    $carouselItemTranslation->carousel_item_id = $this->id;
                    $carouselItemTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $carouselItemTranslation->carousel_item_id = $this->id;
                $carouselItemTranslation->language_id = $language->language_id;
                $carouselItemTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                $carouselItemTranslation->anchor = StringHelper::truncate($this->anchor[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->anchor[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->anchor[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
                $carouselItemTranslation->url = StringHelper::truncate($this->url[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->url[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->url[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
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
                $carouselItemTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($carouselItemTranslation->title) {
                    $this->link('carouselItemTranslations', $carouselItemTranslation);
                }
			}

			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
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

			$dirPath = Yii::getAlias("@uploads/carousel/{$this->uuidOf('carousel_id')}");
			$oldFilePath = "{$dirPath}/{$this->oldAttributes['image']}";
			$fileName = StringHelper::truncate(implode('_', array_filter([
				Inflector::slug($this->translation->title),
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
	 * Saves the model.
	 *
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveCarouselItemTranslations()) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
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
