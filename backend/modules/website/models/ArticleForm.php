<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\models\Language;
use common\models\Article;
use common\models\ArticleCategory;
use common\models\ArticleTranslation;
use common\helpers\StringHelper;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\web\UploadedFile;

class ArticleForm extends Article
{
	/**
	 * @var int|array The article category model ID.
	 */
	public $article_category_id;

	/**
	 * @var UploadedFile The article image file.
	 */
	public $imageFile;

	/**
	 * @var array The multilingual title of the article.
	 */
	public $title = [];

	/**
	 * @var array The multilingual keywords of the article.
	 */
	public $keywords = [];

	/**
	 * @var array The multilingual description of the article.
	 */
	public $description = [];

	/**
	 * @var array The multilingual content of the article.
	 */
	public $content = [];

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
			[['title'], 'required', 'when' => function () {
				return empty($this->title[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
				return attribute.$form.find("[name*=\"[title][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['title', 'keywords', 'description', 'content'], 'each', 'rule' => ['trim']],
			[['keywords', 'description', 'content'], 'each', 'rule' => ['default', 'value' => null]],
			[['icon', 'video', 'source'], 'default', 'value' => null],
			[['video'], 'url'],
			[['imageFile'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'maxFiles' => 1, 'skipOnEmpty' => true],
			[['article_category_id'], 'each', 'rule' => ['exist', 'targetClass' => ArticleCategory::class, 'targetAttribute' => ['article_category_id' => 'id'], 'skipOnError' => true]],
            [['translator'], 'safe'],
        ]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'article_category_id' => Yii::t('label', 'Article Category'),
			'imageFile' => Yii::t('label', 'Image'),
			'title' => Yii::t('label', 'Title'),
			'keywords' => Yii::t('label', 'Keywords'),
			'description' => Yii::t('label', 'Description'),
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

		// Expose the FKs as canonical UUID strings so the dropdown (whose option keys are UUID strings) can match the selected values.
		$this->article_category_id = ArrayHelper::getColumn($this->articleCategories, 'uuid');
		$this->title = ArrayHelper::map($this->articleTranslations, 'language_id', 'title');
		$this->keywords = ArrayHelper::map($this->articleTranslations, 'language_id', 'keywordsList');
		$this->description = ArrayHelper::map($this->articleTranslations, 'language_id', 'description');
		$this->content = ArrayHelper::map($this->articleTranslations, 'language_id', 'content');
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

			$dirPath = Yii::getAlias("@uploads/article/{$this->uuid}");
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
	 * Saves the translations.
	 *
	 * @return bool
	 */
	protected function saveArticleTranslations()
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
				$articleTranslation = ArticleTranslation::findOne([
					'article_id' => $this->id,
					'language_id' => $language->language_id,
				]);
				if (!$articleTranslation) {
					$articleTranslation = new ArticleTranslation();
                    $articleTranslation->article_id = $this->id;
					$articleTranslation->language_id = $language->language_id;
				}

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $articleTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 240, '');
                $articleTranslation->slug = \common\helpers\Inflector::slug($articleTranslation->title) ?: null;
                $articleTranslation->keywords = StringHelper::truncate($this->keywords[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? implode(',', (array) $this->keywords[$language->language_id]) : ($source && $target ? Yii::$app->translate->translate($source, $target, implode(',', (array) $this->keywords[Yii::$app->language]))['data']['translations'][0]['translatedText'] : null), 255, '');
                $articleTranslation->description = StringHelper::truncate($this->description[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->description[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->description[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 255, '');
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
                $articleTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($articleTranslation->title) {
                    $this->link('articleTranslations', $articleTranslation);
                }
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Links the ArticleCategory model.
	 *
	 * @return bool
	 */
	protected function linkArticleCategory()
	{
		try {
			$this->unlinkAll('articleCategories', true);

			$articleCategories = ArticleCategory::find()
				->select(['id'])
				->where([
					'id' => $this->article_category_id,
					'deleted' => ArticleCategory::NO,
				])
				->all();

			foreach ($articleCategories as $articleCategory) {
				$this->link('articleCategories', $articleCategory);
			}
			return true;
		} catch (\Exception $e) {
			$this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$transaction = static::getDb()->beginTransaction();
		try {
			if (!parent::save($runValidation, $attributeNames)) {
				throw new \Exception();
			}
			if (!$this->saveFiles()) {
				throw new \Exception();
			}
			if (!$this->saveArticleTranslations()) {
				throw new \Exception();
			}
			if (!$this->linkArticleCategory()) {
				throw new \Exception();
			}
			$transaction->commit();
			return true;
		} catch(\Exception $e) {
			$transaction->rollBack();
			return false;
		}
	}
}
