<?php

namespace frontend\modules\account\controllers;

use common\models\AffiliateCommission;
use frontend\modules\account\components\AffiliateAreaController;
use yii\data\ActiveDataProvider;

/**
 * The affiliate's commissions, one per paid (or storno) invoice of a referred subscriber.
 */
class AffiliateCommissionController extends AffiliateAreaController
{
	/**
	 * Lists the commissions.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$affiliate = $this->getAffiliate();
		$dataProvider = new ActiveDataProvider([
			'query' => AffiliateCommission::find()
				->andWhere(['affiliate_id' => $affiliate->getAttribute('id')])
				->with(['invoice', 'subscriber.user'])
				->orderBy(['earned_at' => SORT_DESC, 'created_at' => SORT_DESC]),
			'pagination' => [
				'pageSize' => 20,
			],
			'sort' => false,
		]);

		return $this->render('index', [
			'dataProvider' => $dataProvider,
			'totals' => $affiliate->getCommissionTotals(),
		]);
	}
}
