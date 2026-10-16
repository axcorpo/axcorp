<?php

namespace backend\modules\tutorial\models;

use common\components\ApplicationBootstrap;
use common\helpers\ModelHelper;
use common\helpers\StringHelper;
use common\helpers\UploadHelper;
use common\models\Language;
use common\models\Tutorial;
use common\models\TutorialCategory;
use common\models\TutorialTranslation;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class TutorialForm extends Tutorial
{

	// i18n attributes
	public $title;
	public $content;

	/**
	 * @var \yii\web\UploadedFile the instance of the uploaded image file.
	 */
	public $imageFile;

	/**
	 * @var \yii\web\UploadedFile the instance of the uploaded attachment file.
	 */
	public $attachmentFile;

    /**
     * @var array The Category model IDs.
     */
    public $category_id;

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
		$this->type = static::TYPE_VIDEO;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['title'], 'required', 'when' => function ($model) {
				return empty($model->title[Yii::$app->language]);
			}, 'whenClient' => 'function (attribute, value) {
			return attribute.$form.find("[name*=\"[title][' . Yii::$app->language . ']\"]").val() == "";
			}'],
			[['title', 'content'], 'each', 'rule' => ['trim']],
			[['imageFile'], 'file', 'extensions' => ['jpeg', 'jpg', 'png', 'gif'], 'maxFiles' => 1, 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'skipOnEmpty' => true],
			[['attachmentFile'], 'file', 'extensions' => ['mp4'], 'maxFiles' => 1, 'maxSize' => Yii::$app->settings->get('maxFileSize'), 'skipOnEmpty' => true],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => TutorialCategory::class, 'targetAttribute' => ['category_id' => 'id']],
            [['translator'], 'safe'],
        ]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'title' => Yii::t('common', 'Title'),
			'content' => Yii::t('common', 'Content'),
            'translator' => Yii::t('label', 'Translator'),
            'category_id' => Yii::t('label', 'Category'),
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
        $this->title = ArrayHelper::map($this->tutorialTranslations, 'language_id', 'title');
        $this->content = ArrayHelper::map($this->tutorialTranslations, 'language_id', 'content');
        // Expose the FKs as canonical UUID strings so the dropdown (whose option
        // keys are UUID strings) can match the selected values.
        $this->category_id = ArrayHelper::getColumn($this->tutorialCategories, 'uuid');
    }

    /**
     * Saves the translations.
     *
     * @return bool
     */
    protected function saveTutorialTranslations()
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
                $tutorialTranslation = TutorialTranslation::findOne([
                    'tutorial_id' => $this->id,
                    'language_id' => $language->language_id,
                ]);
                if (!$tutorialTranslation) {
                    $tutorialTranslation = new TutorialTranslation();
                    $tutorialTranslation->tutorial_id = $this->id;
                    $tutorialTranslation->language_id = $language->language_id;
                }

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }
                $tutorialTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 240, '');
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
                $tutorialTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($tutorialTranslation->title) {
                    $this->link('tutorialTranslations', $tutorialTranslation);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Links the Category models.
     *
     * @return bool
     * @throws \Throwable
     */
    protected function linkCategories()
    {
        try {
            $this->unlinkAll('tutorialCategories', true);
            if (empty($this->category_id)) {
                return true;
            }
            // getTree() returns model objects (keyed by id) — extract the ids, otherwise
            // the objects land in the where() condition and the lookup always fails.
            $ids = ArrayHelper::getColumn(TutorialCategory::getTree($this->category_id), 'id');
            if (empty($ids)) {
                return true;
            }
            $categories = TutorialCategory::findAll([
                'id' => $ids,
                'status' => TutorialCategory::STATUS_ACTIVE,
                'deleted' => TutorialCategory::NO,
            ]);
            foreach ($categories as $category) {
                $this->link('tutorialCategories', $category);
            }
            return true;
        } catch (\Exception $e) {
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
			$imageFile = UploadedFile::getInstance($this, 'imageFile');
			$attachmentFile = UploadedFile::getInstance($this, 'attachmentFile');
			if (!$imageFile && !$attachmentFile) {
				return true;
			}
			$dirPath = Yii::getAlias("@uploads/tutorial/{$this->uuid}");
			FileHelper::createDirectory($dirPath);
			if ($imageFile) {
				$fileName = "image.{$imageFile->extension}";
				$filePath = "{$dirPath}/{$fileName}";
				if (!$imageFile->saveAs($filePath)) {
					throw new \Exception();
				}
				$this->image = $fileName;
			}
			if ($attachmentFile) {
				$fileName = "file.{$attachmentFile->extension}";
				$filePath = "{$dirPath}/{$fileName}";
				if (!$attachmentFile->saveAs($filePath)) {
					throw new \Exception();
				}
				$this->file = $fileName;
			}
			$this->save(true, ['image', 'file']);
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool
	 * @throws \yii\db\Exception
	 */
	public function saveModel()
	{
		$transaction = static::getDb()->beginTransaction();
		try {
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveTutorialTranslations()) {
				throw new \Exception();
			}
            if (!$this->linkCategories()) {
                throw new \Exception();
            }
			if (!$this->saveFiles()) {
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
