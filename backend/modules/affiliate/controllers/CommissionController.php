<?php

namespace backend\modules\affiliate\controllers;

use backend\controllers\MainController;
use backend\modules\affiliate\models\CommissionSearch;
use common\helpers\UuidHelper;
use common\models\Affiliate;
use common\services\AffiliateCommissionService;
use Yii;
use yii\filters\AccessControl;

/**
 * Affiliate commissions: list + bulk approve (release PENDING before the hold period ends)
 * and bulk cancel (PENDING/PAYABLE not yet in a payout). The status rules live in
 * AffiliateCommissionService; rows it refuses are simply skipped and counted.
 */
class CommissionController extends MainController
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
						'actions' => ['index', 'dt-commissions'],
						'roles' => ['viewAffiliate'],
					],
					[
						'allow' => true,
						'actions' => ['approve', 'cancel'],
						'roles' => ['manageAffiliatePayout'],
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
			'dt-commissions' => CommissionSearch::class,
		];
	}

	/**
	 * Lists the commissions; `affiliate_id` (the link on an affiliate's page) scopes the
	 * list to that affiliate.
	 *
	 * @param string|null $affiliate_id uuid
	 * @return mixed
	 */
	public function actionIndex($affiliate_id = null)
	{
		$affiliate = is_string($affiliate_id) && UuidHelper::isValid($affiliate_id)
			? Affiliate::find()->with('user')->where(['id' => UuidHelper::toBytes($affiliate_id), 'deleted' => Affiliate::NO])->one()
			: null;

		return $this->render('index', [
			'affiliate' => $affiliate,
		]);
	}

	/**
	 * Makes the selected PENDING commissions payable now.
	 *
	 * @return mixed
	 */
	public function actionApprove()
	{
		$ids = $this->getSelection();
		$count = (new AffiliateCommissionService())->approve($ids);

		return $this->respond($count, count($ids));
	}

	/**
	 * Cancels the selected commissions that are not paid nor in a payout.
	 *
	 * @return mixed
	 */
	public function actionCancel()
	{
		$ids = $this->getSelection();
		$count = (new AffiliateCommissionService())->cancel($ids);

		return $this->respond($count, count($ids));
	}

	/**
	 * The selected commission uuids (malformed values dropped).
	 *
	 * @return string[]
	 */
	protected function getSelection()
	{
		return array_values(array_filter((array) Yii::$app->request->post('selection', []), [\common\helpers\UuidHelper::class, 'isValid']));
	}

	/**
	 * @param int $changed
	 * @param int $selected
	 * @return mixed
	 */
	protected function respond($changed, $selected)
	{
		$response = [
			'success' => $changed > 0,
			'message' => [
				'title' => $changed > 0
					? Yii::t('common', 'The operation was successful.')
					: Yii::t('common', 'The operation was unsuccessful.'),
				'body' => [
					Yii::t('common', '{changed} of {selected} selected commissions were changed; the others are not in a state that allows it.', [
						'changed' => $changed,
						'selected' => $selected,
					]),
				],
			],
		];
		if (Yii::$app->request->isAjax) {
			return $this->asJson($response);
		}
		Yii::$app->session->setFlash($response['success'] ? 'success' : 'error', [$response['message']]);

		return $this->redirect(['index']);
	}
}
