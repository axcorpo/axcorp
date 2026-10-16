<?php

namespace backend\modules\affiliate\controllers;

use backend\controllers\MainController;
use backend\modules\affiliate\models\AffiliateForm;
use backend\modules\affiliate\models\AffiliateRejectForm;
use backend\modules\affiliate\models\AffiliateSearch;
use common\models\Affiliate;
use common\services\AffiliateApplicationService;
use common\services\AffiliatePayoutService;
use common\services\AffiliateStatsService;
use Yii;
use yii\base\UserException;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

/**
 * Affiliates: list, create (with their user account), update, view, soft delete/restore,
 * and the review of the applications sent from the public page (approve / reject).
 * Affiliates are never erased — their commissions and payouts reference them.
 */
class AffiliateController extends MainController
{
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::class,
				'rules' => [
					[
						'allow' => true,
						'actions' => ['index', 'view', 'dt-affiliates'],
						'roles' => ['viewAffiliate'],
					],
					[
						'allow' => true,
						'actions' => ['create'],
						'roles' => ['createAffiliate'],
					],
					[
						'allow' => true,
						'actions' => ['update'],
						'roles' => ['updateAffiliate'],
					],
					[
						'allow' => true,
						'actions' => ['approve', 'reject'],
						'roles' => ['updateAffiliate'],
						'verbs' => ['POST'],
					],
					[
						'allow' => true,
						'actions' => ['delete', 'restore'],
						'roles' => ['deleteAffiliate'],
						'verbs' => ['POST'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-affiliates' => AffiliateSearch::class,
		];
	}

	/**
	 * Lists the affiliates (or the deleted ones).
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == Affiliate::YES && !Yii::$app->user->can('deleteAffiliate')) {
			return $this->redirect(['index']);
		}
		return $this->render('index');
	}

	/**
	 * Displays an affiliate: profile, billing company, referral link, subscribers,
	 * commissions, payouts, totals and the charts the affiliate sees on its own dashboard.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);
		// The affiliate's own dashboard window: the last 12 calendar months, this one included.
		$start = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0)->modify('-11 months');

		return $this->render('view', [
			'model' => $model,
			'payableSummary' => (new AffiliatePayoutService())->getPayableSummary($model),
			'stats' => new AffiliateStatsService([
				'affiliateId' => $model->getAttribute('id'),
				'from' => $start,
				'to' => $start->modify('+12 months'),
			]),
			'rejectForm' => new AffiliateRejectForm(),
		]);
	}

	/**
	 * Approves an application from the public page: the affiliate becomes active.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionApprove($id)
	{
		$model = $this->findModel($id);
		return $this->runApplicationAction($model, function () use ($model) {
			(new AffiliateApplicationService())->approve($model);
			return Yii::t('common', 'The affiliate application has been approved.');
		});
	}

	/**
	 * Rejects an application from the public page, with the reason e-mailed to the applicant.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionReject($id)
	{
		$model = $this->findModel($id);
		$form = new AffiliateRejectForm();
		if (!$form->load(Yii::$app->request->post()) || !$form->validate()) {
			$errors = $form->getFirstErrors();
			Yii::$app->session->setFlash('error', $errors ? reset($errors) : Yii::t('common', 'The operation was unsuccessful.'));
			return $this->redirect(['view', 'id' => $model->uuid]);
		}
		return $this->runApplicationAction($model, function () use ($model, $form) {
			(new AffiliateApplicationService())->reject($model, $form->reason);
			return Yii::t('common', 'The affiliate application has been rejected.');
		});
	}

	/**
	 * Runs an application review step, logged like any update, with its outcome flashed.
	 *
	 * @param Affiliate $model
	 * @param callable $action returns the success message
	 * @return \yii\web\Response
	 */
	protected function runApplicationAction(Affiliate $model, callable $action)
	{
		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);
		try {
			$message = $action();
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', $message);
		} catch (UserException $e) {
			Yii::$app->session->setFlash('error', $e->getMessage());
		}
		return $this->redirect(['view', 'id' => $model->uuid]);
	}

	/**
	 * Creates an affiliate and its user account.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new AffiliateForm();
		// Shown pre-filled (generated code, default percentage) so the admin may change them.
		$model->applyDefaults();

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', $model->linkedExistingUser
				? Yii::t('common', 'Record has been created. The affiliate was linked to the existing account with this e-mail.')
				: Yii::t('common', 'Record has been created.'));

			return $this->redirect(['view', 'id' => $model->uuid]);
		}

		return $this->render('create', [
			'model' => $model,
		]);
	}

	/**
	 * Updates an affiliate and its user's name and contact data.
	 *
	 * @param string $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, AffiliateForm::class);

		Yii::$app->eventLog->beginRecord($model);
		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			Yii::$app->eventLog->endRecord();
			Yii::$app->session->setFlash('success', Yii::t('common', 'Record has been updated.'));

			return $this->redirect(['view', 'id' => $model->uuid]);
		}

		return $this->render('update', [
			'model' => $model,
		]);
	}

	/**
	 * Soft-deletes affiliates (always soft: money history references them). Their links stop
	 * attributing and no new commissions are computed; the user account stays.
	 *
	 * @param string|null $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionDelete($id = null)
	{
		return $this->changeDeleted($id, true);
	}

	/**
	 * Restores soft-deleted affiliates.
	 *
	 * @param string|null $id
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	public function actionRestore($id = null)
	{
		return $this->changeDeleted($id, false);
	}

	/**
	 * @param string|null $id
	 * @param bool $delete
	 * @return mixed
	 * @throws NotFoundHttpException
	 */
	protected function changeDeleted($id, $delete)
	{
		if ($id === null) {
			$id = (array) Yii::$app->request->post('selection');
		}
		$models = $this->findModel($id, null, true);
		$response = [
			'success' => true,
			'message' => [
				'title' => $delete
					? Yii::t('common', 'The delete operation was successful.')
					: Yii::t('common', 'The restore operation was successful.'),
				'body' => [],
			],
		];
		$dbTransaction = Yii::$app->db->beginTransaction();
		try {
			/** @var Affiliate $model */
			foreach ($models->each() as $model) {
				Yii::$app->eventLog
					->setData([
						'operation' => $delete ? (Yii::$app->eventLog)::ACTION_SOFT_DELETE : (Yii::$app->eventLog)::ACTION_UPDATE,
					])
					->beginRecord($model);
				if (!($delete ? $model->softDelete() : $model->restore())) {
					throw new \Exception();
				}
				Yii::$app->eventLog->endRecord();
			}
			$dbTransaction->commit();
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			$response['success'] = false;
			$response['message']['title'] = $delete
				? Yii::t('common', 'The delete operation was unsuccessful.')
				: Yii::t('common', 'The restore operation was unsuccessful.');
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}
		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
	}

	/**
	 * Finds the Affiliate model(s) by uuid.
	 *
	 * @param string|array $id
	 * @param string|null $modelName
	 * @param bool $asActiveQuery all matches, deleted included (bulk delete/restore)
	 * @return \yii\db\ActiveQuery|Affiliate|AffiliateForm
	 * @throws NotFoundHttpException
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = $modelName && class_exists($modelName) ? $modelName : Affiliate::class;
		$query = $modelName::find()->andWhere(['id' => $id]);

		if ($asActiveQuery) {
			if (!$id || !$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => Affiliate::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
