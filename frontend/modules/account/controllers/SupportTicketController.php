<?php

namespace frontend\modules\account\controllers;

use common\models\SupportTicket;
use common\models\SupportTicketDepartment;
use common\models\SupportTicketPriority;
use common\models\SupportTicketStatus;
use frontend\controllers\MainController;
use frontend\modules\account\models\SupportTicketCommentForm;
use frontend\modules\account\models\SupportTicketForm;
use frontend\modules\account\models\SupportTicketSearch;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;

class SupportTicketController extends MainController
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
						'actions' => ['index', 'dt-support-tickets', 'view', 'create', 'update', 'delete-file'],
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 * @throws NotFoundHttpException
	 */
	public function beforeAction($action)
	{
		if (!Yii::$app->request->isAjax && $action->id !== 'index') {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		return parent::beforeAction($action);
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-support-tickets' => SupportTicketSearch::class,
		];
	}

	/**
	 * Lists all SupportTicket models.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->get('deleted') == SupportTicket::YES) {
			if (!Yii::$app->settings->get('enableSoftDelete')) {
				return $this->redirect(['index']);
			}
		}
		return $this->render('index');
	}

	/**
	 * Displays a single SupportTicket model.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionView($id)
	{
		$model = $this->findModel($id);

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => true,
				'data' => $this->renderAjax('view', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('view', [
			'model' => $model,
		]);
	}

	/**
	 * Creates a new SupportTicket model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 *
	 * @return mixed
	 */
	public function actionCreate()
	{
		$model = new SupportTicketForm();
		$model->user_id = Yii::$app->user->id;
		// Preselect the defaults as canonical UUID strings so the dropdowns (whose option
		// keys are UUID strings) can match them; beforeSave converts back to bytes.
		$model->support_ticket_department_id = SupportTicketDepartment::findDefaultSupportTicketDepartment()->uuid;
		$model->support_ticket_priority_id = SupportTicketPriority::findDefaultSupportTicketPriority()->uuid;
		$model->support_ticket_status_id = SupportTicketStatus::findDefaultSupportTicketStatus()->uuid;
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSupportTickets']));
			$model->sendNotification();

			$message = Yii::t('common', 'We received your support ticket and we\'ll answer you as soon as possible.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'id' => $model->uuid,
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['view', 'id' => $model->id]);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('create', [
					'model' => $model,
				]),
			]);
		}

		return $this->render('create', [
			'model' => $model,
		]);
	}

	/**
	 * Updates an existing SupportTicket model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionUpdate($id)
	{
		$model = $this->findModel($id, SupportTicketForm::class);
		$supportTicketCommentModel = new SupportTicketCommentForm();
		$supportTicketCommentModel->support_ticket_id = $model->id;
		$result = true;

		if ($model->load(Yii::$app->request->post()) && ($result = $model->save())) {
			if ($supportTicketCommentModel->load(Yii::$app->request->post())) {
				$result = $supportTicketCommentModel->save();
			}
			Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllSupportTickets']));
			$model->sendNotification($supportTicketCommentModel);

			$message = Yii::t('common', 'Thank you for your message. We\'ll answer you as soon as possible.');
			if (Yii::$app->request->isAjax) {
				return $this->asJson([
					'success' => true,
					'message' => $message,
				]);
			}
			Yii::$app->session->setFlash('success', $message);

			return $this->redirect(['index']);
		}

		if (Yii::$app->request->isAjax) {
			return $this->asJson([
				'success' => (bool) $result,
				'data' => $this->renderAjax('update', [
					'model' => $model,
					'supportTicketCommentModel' => $supportTicketCommentModel,
				]),
			]);
		}

		return $this->render('update', [
			'model' => $model,
			'supportTicketCommentModel' => $supportTicketCommentModel,
		]);
	}

	/**
	 * Delete an existing file.
	 *
	 * @param $id
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionDeleteFile($id)
	{
		if (!Yii::$app->request->isAjax) {
			throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
		}
		$model = $this->findModel($id);
		$fileName = Yii::$app->request->post('key');
		$attachments = (array) $model->getUnserializedValue('attachment');
		// Only a file this ticket actually lists may be deleted, and never through a path:
		// the posted key used to be joined into the path as-is ("../../..").
		if (!is_string($fileName) || $fileName !== basename($fileName) || !in_array($fileName, $attachments, true)) {
			return $this->asJson([
				'success' => false,
				'message' => Yii::t('common', 'Cannot delete the record.'),
			]);
		}
		// Built from the model, not the request, and in the uuid directory saveFiles() uses.
		$filePath = Yii::getAlias("@uploads/support-ticket/{$model->uuid}/" . basename($fileName));
		$result = true;

		if (file_exists($filePath) && ($result = FileHelper::unlink($filePath))) {
			if (($key = array_search($fileName, $attachments, true)) !== false) {
				unset($attachments[$key]);
			}
			$model->updateAttributes(['attachment' => @serialize($attachments)]);
		}

		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been deleted.') :
				Yii::t('common', 'Cannot delete the record.'),
		]);
	}

	/**
	 * Finds the SupportTicket model(s) based on its primary key value.
	 * A 404 HTTP exception will be thrown if no record was found.
	 *
	 * @param int|array $id
	 * @param \yii\db\ActiveRecord|null $modelName
	 * @param bool $asActiveQuery
	 * @return \yii\db\ActiveQuery|\yii\db\ActiveRecord|SupportTicket|SupportTicketForm
	 * @throws NotFoundHttpException if no model was found
	 */
	protected function findModel($id, $modelName = null, $asActiveQuery = false)
	{
		$modelName = $modelName !== null && class_exists($modelName) ? $modelName : SupportTicket::class;
		$query = $modelName::find()->andWhere([
			'id' => $id,
			'created_by' => Yii::$app->user->id,
		]);

		if ($asActiveQuery) {
			if (!$query->count()) {
				throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
			}
			return $query;
		}

		$query->andWhere(['deleted' => SupportTicket::NO]);
		if (($model = $query->one()) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}
}
