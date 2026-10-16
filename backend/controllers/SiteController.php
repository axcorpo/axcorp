<?php

namespace backend\controllers;

use backend\models\SearchForm;
use backend\models\UserProfileForm;
use common\filters\RateLimit;
use common\models\County;
use common\models\Locality;
use kartik\depdrop\DepDropAction;
use tws\helpers\Url;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

class SiteController extends MainController
{
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			// The admin login was the one credential form with nothing in front of it,
			// and it is the most valuable one in the system.
			'rateLimit' => [
				'class' => RateLimit::class,
				'only' => ['login'],
				'limit' => 10,
				'window' => 900,
				'identityParams' => ['username', 'email'],
				'identityLimit' => 10,
				'identityWindow' => 3600,
				'keyPrefix' => 'ratelimit:admin',
			],
			'access' => [
				'class' => AccessControl::class,
				'rules' => [
					[
						'actions' => ['login'],
						'allow' => true,
						'roles' => ['?'],
					],
					[
						'actions' => ['counties', 'localities'],
						'allow' => true,
						'roles' => ['@', '?'],
					],
					[
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => VerbFilter::class,
				'actions' => [
					'logout' => ['POST'],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeAction($action)
	{
		// A rejected POST renders the error page on that same token-less request, so
		// re-validating there would raise a second exception ("error while handling
		// another error", HTTP 500) instead of the clean 400 the rejection should
		// produce. The DepDrop lookups below stay validated: they are same-origin admin
		// XHRs, so yii.js supplies the token.
		if ($action->id === 'error') {
			$this->enableCsrfValidation = false;
		}
		return parent::beforeAction($action);
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
				'layout' => 'blank',
			],
			'counties' => [
				'class' => DepDropAction::class,
				'outputCallback' => function ($selectedId, $params) {
					return County::queryCountiesByCountry($selectedId);
				},
				'selectedCallback' => function ($selectedId) {
					if (Yii::$app->request->post('depdrop_all_params')['dd_country']) {
						return Yii::$app->request->post('depdrop_all_params')['dd_country'];
					}
					if (count(County::queryCountiesByCountry($selectedId)) == 1) {
						return County::queryCountiesByCountry($selectedId)[0];
					}
					return [];
				},
			],
			'localities' => [
				'class' => DepDropAction::class,
				'outputCallback' => function ($selectedId, $params) {
					return Locality::queryLocalitiesByCounty($selectedId);
				},
				'selectedCallback' => function ($selectedId) {
					if (Yii::$app->request->post('depdrop_all_params')['dd_locality']) {
						return Yii::$app->request->post('depdrop_all_params')['dd_locality'];
					}
					if (count(Locality::queryLocalitiesByCounty($selectedId)) == 1) {
						return Locality::queryLocalitiesByCounty($selectedId)[0];
					}
					return [];
				},
			],
		];
	}

	/**
	 * Displays homepage.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$subscriptionReportModel = new \backend\modules\subscriber\modules\report\models\SubscriptionReportForm();
		$subscriptionMonthlyModel = new \backend\modules\subscriber\modules\report\models\SubscriptionMonthlyReportForm();
		$subscriptionStatusReportModel = new \backend\modules\subscriber\modules\report\models\SubscriptionStatusReportForm();
		$workspaceTypeReportModel = new \backend\modules\subscriber\modules\report\models\WorkspaceTypeReportForm();

		return $this->render('index', [
			'dataset' => [
				'subscription' => $subscriptionReportModel->getDataset(),
				'subscriptionMonthly' => $subscriptionMonthlyModel->getDataset(),
				'subscriptionStatus' => $subscriptionStatusReportModel->getDataset(),
				'workspaceType' => $workspaceTypeReportModel->getDataset(),
			],
		]);
	}

	/**
	 * Displays login page.
	 *
	 * @return mixed
	 */
	public function actionLogin()
	{
		return $this->redirect(Url::to(['/site/login'], true, '@frontend'), 301);
	}

	/**
	 * Logs out the current user.
	 *
	 * @return mixed
	 */
	public function actionLogout()
	{
		// Signs out here: the frontend logout is POST-only, and a redirect would reach it as
		// a GET. Backend and frontend share the session and the identity cookie, and the
		// rotated auth_key invalidates the API bearer token and any "remember me" cookie.
		/** @var \common\models\User|null $user */
		$user = Yii::$app->user->identity;
		Yii::$app->user->logout();
		if ($user !== null) {
			$user->rotateAuthKey();
		}

		return $this->redirect(Url::to(['/site/index'], true, '@frontend'));
	}

	/**
	 * Displays profile page.
	 *
	 * @return mixed
	 */
	public function actionProfile()
	{
		$model = UserProfileForm::findOne(Yii::$app->user->id);

		if ($model->load(Yii::$app->request->post()) && $model->saveModel()) {
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));
			return $this->refresh();
		}

		return $this->render('profile', [
			'model' => $model
		]);
	}

	/**
	 * Searches globally in the site.
	 *
	 * @return mixed
	 */
	public function actionSearch()
	{

		$dataset = [];

		// A locality request sends `county` as its filter, not as a second query, so
		// the county branch stays out of it - otherwise the locality suggestions also
		// offered the county's own name.
		if (!Yii::$app->request->post('locality', null) && ($criteria = Yii::$app->request->post('county', null))) {
			// array_filter drops the country when none is chosen: findByCriteria() turns
			// whatever it is handed into a WHERE, so a null country_code searched for
			// counties with country_code IS NULL and always came back empty.
			$params = array_filter([
				'country_code' => Yii::$app->request->post('country_code', null),
			]);
			$counties = \common\models\County::findByCriteria($criteria, $params);
			foreach ($counties as $county) {
				$dataset[] = [
					'label' => $county->name,
				];
			}
		}

		if ($criteria = Yii::$app->request->post('locality', null)) {
			// Same here: with no county typed, an unfiltered locality search beats one
			// that looks for localities whose county IS NULL.
			$params = array_filter([
				'county' => Yii::$app->request->post('county', null),
			]);
			$localities = \common\models\Locality::findByCriteria($criteria, $params);
			foreach ($localities as $locality) {
				$dataset[] = [
					'label' => $locality->name,
				];
			}
		}

		if ($criteria = Yii::$app->request->post('county', null) || $criteria = Yii::$app->request->post('locality', null)) {
			return $this->asJson([
				'results' => $dataset,
			]);
		}

		$searchModel = new SearchForm();
		$searchModel->load(Yii::$app->request->get());

		$dataProvider = $searchModel->search();

		return $this->render('search', [
			'searchModel' => $searchModel,
			'dataProvider' => $dataProvider,
		]);
	}
}
