<?php

namespace frontend\modules\account\models;

use common\models\Affiliate;
use common\models\Subscriber;
use common\models\Subscription;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;

/**
 * The master subscribers an affiliate referred (subscriber.affiliate_id), as the affiliate
 * may see them.
 *
 * The view renders only what [[formatRow()]] returns, so the full name, e-mail and phone
 * of a subscriber never reach the page (GDPR: the affiliate sees masked data only).
 */
class AffiliateSubscriberSearch extends BaseObject
{
	/**
	 * @var Affiliate
	 */
	public $affiliate;

	/**
	 * @var int
	 */
	public $pageSize = 20;

	/**
	 * @return ActiveDataProvider of Subscriber, the affiliate's only
	 */
	public function search()
	{
		return new ActiveDataProvider([
			'query' => Subscriber::find()
				->andWhere([
					'affiliate_id' => $this->affiliate->getAttribute('id'),
					'deleted' => Subscriber::NO,
				])
				->with([
					'user',
					'subscriptions' => function ($query) {
						$query->andWhere(['deleted' => Subscription::NO])
							->with(['package.packageTranslations'])
							->orderBy(['start_at' => SORT_DESC]);
					},
				])
				->orderBy(['created_at' => SORT_DESC]),
			'pagination' => [
				'pageSize' => $this->pageSize,
			],
			'sort' => false,
		]);
	}

	/**
	 * The display values of one subscriber (masked), with their subscriptions.
	 *
	 * @param Subscriber $model
	 * @return array
	 */
	public static function formatRow(Subscriber $model)
	{
		$subscriptions = [];
		$statuses = Subscription::getStatusLabels();
		foreach ($model->subscriptions as $subscription) {
			if ((int) $subscription->deleted === Subscription::YES) {
				continue;
			}
			$status = $statuses[(int) $subscription->status] ?? null;
			$subscriptions[] = [
				'package' => static::packageName($subscription),
				'price' => $subscription->currency
					? Yii::$app->formatter->asCurrency($subscription->price, $subscription->currency)
					: Yii::$app->formatter->asDecimal($subscription->price, 2),
				'status' => $status ? $status['label'] : (string) $subscription->status,
				'statusColor' => $status ? $status['color'] : 'secondary',
				'startAt' => $subscription->start_at,
				'endAt' => $subscription->end_at,
			];
		}

		return [
			'name' => $model->getMaskedName(),
			'email' => $model->getMaskedEmail(),
			'signupAt' => $model->created_at,
			'subscriptions' => $subscriptions,
		];
	}

	/**
	 * The package name of a subscription in the application language, falling back to any
	 * translation (Package::getTranslation() fails on a missing language).
	 *
	 * @param Subscription $subscription
	 * @return string
	 */
	protected static function packageName(Subscription $subscription)
	{
		$package = $subscription->package;
		if ($package === null) {
			return '';
		}
		$fallback = '';
		foreach ($package->packageTranslations as $translation) {
			if ($translation->language_id === Yii::$app->language) {
				return (string) $translation->name;
			}
			if ($fallback === '') {
				$fallback = (string) $translation->name;
			}
		}
		return $fallback;
	}
}
