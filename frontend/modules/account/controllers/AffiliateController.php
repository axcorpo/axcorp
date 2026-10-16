<?php

namespace frontend\modules\account\controllers;

use frontend\modules\account\components\AffiliateAreaController;
use frontend\modules\account\models\AffiliateDashboard;

/**
 * The affiliate dashboard: the main figures and the referral links to copy.
 */
class AffiliateController extends AffiliateAreaController
{
	/**
	 * Displays the dashboard.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$affiliate = $this->getAffiliate();

		return $this->render('index', [
			'affiliate' => $affiliate,
			'dashboard' => new AffiliateDashboard(['affiliate' => $affiliate]),
		]);
	}
}
