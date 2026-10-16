<?php

namespace frontend\modules\service\controllers;

use common\models\Service;
use common\models\ServiceCategory;
use common\models\ServiceTranslation;
use frontend\controllers\MainController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class DefaultController extends MainController
{
	/**
	 * Displays index view.
	 *
	 * @param null|string $category
	 * @return mixed
	 */
	public function actionIndex($category = null)
	{
		$view = $this->getView();
		$canonicalUrl = Url::canonical();
		$model = null;
		$modelTranslation = null;

		if (!empty($category) && ($model = ServiceCategory::findServiceCategoryBySlug($category))) {
			$modelTranslation = $model->getTranslation();
			$view->title = Yii::t('frontend', 'Features from {0} module', $modelTranslation->title);
		} else {
			if ($page = \common\models\Page::findPageByRoute(["/{$this->module->id}/{$this->id}/index"])) {
				$modelTranslation = $page->getTranslation();
				$view->title = $modelTranslation->title;
			}
		}

		// Standard meta tags
		$view->registerMetaTag(['name' => 'description', 'content' => $modelTranslation->description], 'description');
		$view->registerMetaTag(['name' => 'keywords', 'content' => $modelTranslation->keywords], 'keywords');
		$view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl], 'canonical');

		// Basic metadata for open graph
		$view->registerMetaTag(['property' => 'og:type', 'content' => 'service'], 'og:type');
		$view->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl], 'og:url');
		$view->registerMetaTag(['property' => 'og:title', 'content' => $modelTranslation->title], 'og:title');
		$view->registerMetaTag(['property' => 'og:description', 'content' => $modelTranslation->description], 'og:description');

		$dataProvider = new ActiveDataProvider([
			'query' => Service::provideServices($model->id),
			'pagination' => [
				'pageSize' => 0,
			],
		]);

		return $this->render('index', [
			'dataProvider' => $dataProvider,
		]);
	}

	/**
	 * Displays a single Service model.
	 *
	 * @param string $slug
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionView($slug)
	{
		$model = $this->findModel($slug);
		$model->updateAttributes(['views' => ++$model->views]);

		$modelTranslation = $model->getTranslation();
		$view = $this->getView();
		$canonicalUrl = Url::canonical();

		// Standard meta tags
		$view->title = $modelTranslation->title;
		$view->registerMetaTag(['name' => 'description', 'content' => $modelTranslation->description], 'description');
		$view->registerMetaTag(['name' => 'keywords', 'content' => $modelTranslation->keywords], 'keywords');
		$view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl], 'canonical');

		// Basic metadata for open graph
		$view->registerMetaTag(['property' => 'og:type', 'content' => 'service'], 'og:type');
		$view->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl], 'og:url');
		$view->registerMetaTag(['property' => 'og:title', 'content' => $modelTranslation->title], 'og:title');
		$view->registerMetaTag(['property' => 'og:description', 'content' => $modelTranslation->description], 'og:description');
		if ($ogLogo = $model->getImageUrl(true)) {
			$view->registerMetaTag(['property' => 'og:image', 'content' => $ogLogo], 'og:image');
		}

		return $this->render('view', [
			'model' => $model,
			'modelTranslation' => $model->getTranslation(),
		]);
	}

	/**
	 * Finds the Service model based on its slug value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param string $slug
	 * @return array|\yii\db\ActiveRecord|Service
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($slug)
	{
		$model = Service::find()
			->alias('a')
			->joinWith([
				'serviceTranslations at' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'at.language_id' => Yii::$app->language,
						'at.deleted' => ServiceTranslation::NO,
					]);
				},
			])
			->andWhere([
				'at.slug' => $slug,
				'a.status' => Service::STATUS_ACTIVE,
				'a.deleted' => Service::NO,
			])
			->limit(1);

		if (($model = $model->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
