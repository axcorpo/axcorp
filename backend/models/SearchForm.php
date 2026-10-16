<?php

namespace backend\models;

use common\models\Article;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;

class SearchForm extends Model
{
	/**
	 * @var string $q The search criterion.
	 */
	public $q;

	/**
	 * @inheritdoc
	 */
	public function formName()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['q'], 'required'],
			[['q'], 'string', 'max' => 255],
			[['q'], 'trim'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'q' => Yii::t('label', 'Criterion'),
		];
	}

	/**
	 * Searches the Input models.
	 *
	 * @return \yii\data\ActiveDataProvider
	 */
	public function search()
	{
		$query = Article::find()
			->alias('a')
			->joinWith([
				'articleTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'at.language_id' => Yii::$app->language,
						'at.deleted' => ArticleTranslation::NO,
					]);
				},
			])
			->where([
				'a.deleted' => Article::NO,
			])
			->andFilterWhere([
				'OR',
				['LIKE', 'at.title', $this->q],
				['LIKE', 'at.keywords', $this->q],
				['LIKE', 'at.description', $this->q],
				['LIKE', 'at.content', $this->q],
			]);

		return new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'defaultPageSize' => Yii::$app->settings->get('itemsPerPage'),
			],
			'sort' => [
				'defaultOrder' => [
					'created_at' => SORT_DESC,
				],
			],
		]);
	}
}
