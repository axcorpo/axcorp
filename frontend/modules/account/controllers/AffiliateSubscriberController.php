<?php

namespace frontend\modules\account\controllers;

use frontend\modules\account\components\AffiliateAreaController;
use frontend\modules\account\models\AffiliateSubscriberSearch;

/**
 * The subscribers the affiliate referred, with masked personal data.
 */
class AffiliateSubscriberController extends AffiliateAreaController
{
	/**
	 * Lists the referred subscribers.
	 *
	 * @return mixed
	 */
	public function actionIndex()
	{
		$search = new AffiliateSubscriberSearch(['affiliate' => $this->getAffiliate()]);

		return $this->render('index', [
			'dataProvider' => $search->search(),
		]);
	}
}
