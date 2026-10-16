<?php

namespace backend\modules\setting\modules\language\models;

use common\models\Language;
use common\models\LanguageSource;
use common\models\LanguageTranslate;
use common\widgets\datatable\DataTableAction;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class LanguageSearch extends DataTableAction
{
	private $_languageSourcesCount;
	private $_translationProgress;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->query = Language::find()->with(['languageTranslates']);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		return ArrayHelper::toArray($query->all(), [
			Language::class => [
				'language_id',
				'language',
				'country',
				'name',
				'name_ascii',
				'status',
				'progress' => function ($model) {
					// Return 0 if no sources were found
					if ($this->getLanguageSourceCount() == 0) {
						return 0;
					}
					// Return translations percentage
					return $this->getTranslationProgress($model->language_id);
				},
			],
		]);
	}

	/**
	 * Gets the LanguageSource model count.
	 *
	 * @return int|string
	 */
	public function getLanguageSourceCount()
	{
		if (!isset($this->_languageSourcesCount)) {
			$this->_languageSourcesCount = LanguageSource::find()->select(['id'])->count();
		}
		return $this->_languageSourcesCount;
	}

	/**
	 * Gets the LanguageSource model translation progress for a specific language.
	 *
	 * @param string $language_id
	 * @return int
	 * @throws \yii\db\Exception
	 */
	public function getTranslationProgress($language_id)
	{
		if (!isset($this->_translationProgress)) {
			// Translation progress array
			$progress = [];
			// LanguageTranslate models
			$languageTranslates = LanguageTranslate::find()
				->select([
					'language',
					'COUNT(*) AS count',
				])
				->andWhere('translation IS NOT NULL')
				->groupBy(['language'])
				->createCommand()
				->queryAll();
			// Compute the LanguageTranslate progress
			foreach ($languageTranslates as $languageTranslate) {
				$progress[$languageTranslate['language']] = floor(($languageTranslate['count'] / $this->getLanguageSourceCount()) * 100);
			}
			// Cache the translation progress array
			$this->_translationProgress = $progress;
		}
		// Return the progress or 0 if not exist
		return isset($this->_translationProgress[$language_id]) ? $this->_translationProgress[$language_id] : 0;
	}
}
