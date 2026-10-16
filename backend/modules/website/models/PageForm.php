<?php

namespace backend\modules\website\models;

use common\components\ApplicationBootstrap;
use common\models\Language;
use common\models\MenuItem;
use common\models\Page;
use common\models\PageTranslation;
use common\helpers\StringHelper;
use Yii;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use common\helpers\Inflector;

class PageForm extends Page
{
	/**
	 * @var array The multilingual title of the page.
	 */
	public $title = [];

	/**
	 * @var array The multilingual slug of the page.
	 */
	public $slug = [];

	/**
	 * @var array The multilingual keywords of the page.
	 */
	public $keywords = [];

	/**
	 * @var array The multilingual description of the page.
	 */
	public $description = [];

	/**
	 * @var array The multilingual content of the page.
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

		$this->controller = 'site';
		$this->action = 'page';
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
			[['module'], 'default', 'value' => null],
			[['title', 'slug', 'keywords', 'description', 'content'], 'each', 'rule' => ['trim']],
			[['keywords', 'description', 'content'], 'each', 'rule' => ['default', 'value' => null]],
            [['translator'], 'safe'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'title' => Yii::t('label', 'Title'),
			'slug' => Yii::t('label', 'Slug'),
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

		$this->title = ArrayHelper::map($this->pageTranslations, 'language_id', 'title');
		$this->slug = ArrayHelper::map($this->pageTranslations, 'language_id', 'slug');
		$this->keywords = ArrayHelper::map($this->pageTranslations, 'language_id', 'keywordsList');
		$this->description = ArrayHelper::map($this->pageTranslations, 'language_id', 'description');
		$this->content = ArrayHelper::map($this->pageTranslations, 'language_id', 'content');
	}

	/**
	 * Saves the translations.
	 *
	 * @return bool
	 */
	protected function savePageTranslations()
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
            foreach (array_keys(\common\models\Page::getShortCodeItems()) as $key) {
                $shortCodes[$key] = md5($key);
            }
            $reverseShortCodes = array_flip($shortCodes);

			$source = $target = null;

			foreach (Language::findAllLanguages() as $language) {
				$pageTranslation = PageTranslation::findOne([
					'page_id' => $this->id,
					'language_id' => $language->language_id,
				]);
				if (!$pageTranslation) {
					$pageTranslation = new PageTranslation();
                    $pageTranslation->page_id = $this->id;
					$pageTranslation->language_id = $language->language_id;
				}

                if (!empty($this->translator) && $language->language_id != Yii::$app->language && in_array($defaultLanguage->language, $languages) && in_array($language->language, $languages)) {
                    $source = $defaultLanguage->language;
                    $target = $language->language;
                }

                $pageTranslation->title = StringHelper::truncate($this->title[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->title[$language->language_id] : ($source && $target ? Yii::$app->translate->translate($source, $target, $this->title[Yii::$app->language])['data']['translations'][0]['translatedText'] : null), 240, '');
                $pageTranslation->slug = Inflector::slug($pageTranslation->title) ?: null;
                $pageTranslation->keywords = StringHelper::truncate($this->keywords[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? implode(',', (array) $this->keywords[$language->language_id]) : ($source && $target ? Yii::$app->translate->translate($source, $target, implode(',', (array) $this->keywords[Yii::$app->language]))['data']['translations'][0]['translatedText'] : null), 255, '');
                $pageTranslation->description = StringHelper::truncate($this->description[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->description[$language->language_id] : ($source && $target ? strtr(Yii::$app->translate->translate($source, $target, strtr($this->description[Yii::$app->language], $shortCodes))['data']['translations'][0]['translatedText'], $reverseShortCodes) : null), 255, '');
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
                $pageTranslation->content = $this->content[$language->language_id] && ($language->language_id == Yii::$app->language || !in_array(2, (array)$this->translator)) ? $this->content[$language->language_id] : (!empty($translations) ? implode('.', $translations) : null);
                if ($pageTranslation->title) {
                    $this->link('pageTranslations', $pageTranslation);
                }
			}
			return true;
		} catch (\Exception $e) {
            $this->addError('', $e->getMessage());
			return false;
		}
	}

	/**
	 * Updates the menu item slugs.
	 *
	 * @return bool
	 */
	protected function updateMenuItemsSlugs()
	{
		/** @var MenuItem[] $menuItems */
		$menuItems = MenuItem::find()
			->alias('mi')
			->joinWith([
				'menuItemTranslations mit',
			])
			->andWhere([
				'OR',
				['=', 'mi.page_id', $this->id],
				['=', 'mi.parent_id', $this->id],
				['IS NOT', 'mi.parent_id', null], // TODO: maybe join with subquery that searches for subpages
			])
			->all();

		if (!$menuItems) {
			return true;
		}
$v = [];
		try {
//			foreach ($menuItems as $menuItem) {
//				foreach ($menuItem->menuItemTranslations as $menuItemTranslation) {
//					$v[] = $menuItem->getNestedSlug($menuItemTranslation->language_id);
//					if ($slug = $menuItem->getNestedSlug($menuItemTranslation->language_id)) {
//						$menuItemTranslation->slug = $slug;
//						if (!$menuItemTranslation->save()) {
//							$this->addErrors($menuItemTranslation->getErrors());
//							throw new \Exception($menuItemTranslation->getErrorSummary(false)[0]);
//						}
//					}
//				}
//			}
//echo '<pre>'; print_r($v); die();
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
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->savePageTranslations()) {
				throw new \Exception();
			}
			if (!$this->updateMenuItemsSlugs()) {
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
