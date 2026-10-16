<?php

namespace backend\modules\affiliate\models;

use common\helpers\DateHelper;
use common\models\PackageTranslation;
use common\models\Subscriber;
use common\models\Subscription;
use common\widgets\datatable\DataTableAction;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * DataTable of the master subscribers the affiliates referred (subscriber rows with an
 * affiliate_id, set once at signup) and their subscriptions: package, price, currency,
 * billing period and status. Read-only — the subscribers are managed in their own module.
 *
 * Name, email and phone come from the subscriber's user account.
 *
 * Fixed context: affiliate_id; the rest are the DataTable column filters (affiliate as a
 * select, subscriptions by status, dates in the `dateFormat` setting).
 */
class SubscriberSearch extends DataTableAction
{
	use AffiliateDataTableTrait;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		$this->assertCan('viewAffiliate');

		$this->query = Subscriber::find()
			->alias('s')
			->joinWith(['user u', 'affiliate a', 'affiliate.user au'])
			->with(['subscriptions.package.packageTranslations'])
			->andWhere(['s.deleted' => Subscriber::NO])
			->andWhere(['IS NOT', 's.affiliate_id', null]);

		// The fixed context of the affiliate view; the rest are column filters.
		$this->query->andFilterWhere(['s.affiliate_id' => $this->getUuidFilter('affiliate_id')]);
	}

	/**
	 * @inheritdoc
	 */
	public function formatData(ActiveQuery $query, $columns)
	{
		$canViewSubscriber = Yii::$app->user->can('viewSubscriber');

		return ArrayHelper::toArray($query->all(), [
			Subscriber::class => [
				'id' => function (Subscriber $model) {
					return $model->uuid;
				},
				'affiliate' => function (Subscriber $model) {
					if (!$model->affiliate) {
						return '&mdash;';
					}
					$label = Html::encode(trim(($model->affiliate->user ? $model->affiliate->user->getFullName() : '') . ' (' . $model->affiliate->code . ')'));
					return Html::a($label, ['/affiliate-manager/affiliate/view', 'id' => $model->affiliate->uuid], ['data-pjax' => 0]);
				},
				'name' => function (Subscriber $model) use ($canViewSubscriber) {
					$name = $model->user ? Html::encode($model->user->getFullName()) : '';
					if ($name === '') {
						return '&mdash;';
					}
					return $canViewSubscriber
						? Html::a($name, ['/subscriber-manager/subscriber/view', 'id' => $model->uuid], ['data-pjax' => 0])
						: $name;
				},
				'email' => function (Subscriber $model) {
					return $model->user && $model->user->email ? Html::encode($model->user->email) : '&mdash;';
				},
				'phone' => function (Subscriber $model) {
					return $model->user && $model->user->phone ? Html::encode($model->user->phone) : '&mdash;';
				},
				'subscriptions' => function (Subscriber $model) {
					return static::formatSubscriptions($model->subscriptions);
				},
				'created_at' => function (Subscriber $model) {
					return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
				},
			],
		]);
	}

	/**
	 * One line per subscription: package, price/currency, billing period, start–end, status.
	 *
	 * @param Subscription[] $subscriptions
	 * @return string
	 */
	public static function formatSubscriptions(array $subscriptions)
	{
		$lines = [];
		foreach ($subscriptions as $subscription) {
			if ((int) $subscription->deleted === Subscription::YES) {
				continue;
			}
			$period = $subscription->billing_period ? "{$subscription->billing_period} {$subscription->billing_cycle}" : '';
			$dates = array_filter([
				$subscription->start_at ? Yii::$app->formatter->asDate($subscription->start_at) : null,
				$subscription->end_at ? Yii::$app->formatter->asDate($subscription->end_at) : null,
			]);
			$lines[] = implode(' &middot; ', array_filter([
				Html::encode(static::packageName($subscription) ?: '—'),
				Html::encode(Yii::$app->formatter->asDecimal($subscription->price, 2) . ' ' . $subscription->currency),
				Html::encode($period),
				Html::encode(implode(' – ', $dates)),
				static::statusLabelInline(Subscription::getStatusLabels(), $subscription->status),
			]));
		}
		return $lines ? implode('<br>', $lines) : '&mdash;';
	}

	/**
	 * The subscription's package name in the application language (else any translation).
	 *
	 * @param Subscription $subscription
	 * @return string|null
	 */
	protected static function packageName(Subscription $subscription)
	{
		$package = $subscription->package;
		if (!$package) {
			return null;
		}
		$translations = ArrayHelper::index($package->packageTranslations, 'language_id');
		$translation = $translations[Yii::$app->language] ?? reset($translations);
		return $translation ? $translation->name : null;
	}

	/**
	 * @param array $labels
	 * @param int $status
	 * @return string
	 */
	protected static function statusLabelInline(array $labels, $status)
	{
		$label = $labels[$status] ?? null;
		return $label ? Html::tag('span', $label['label'], ['class' => 'label label-' . $label['color']]) : '';
	}

	/**
	 * @inheritdoc
	 */
	public function applyFilter(ActiveQuery $query, $columns, $search)
	{
		foreach ($columns as $column) {
			if ($column['searchable'] == 'false') {
				continue;
			}
			if (!empty($search['value'])) {
				$value = trim($search['value']);
				$filterOperator = 'orFilterWhere';
			} else {
				$value = trim($column['search']['value']);
				$filterOperator = 'andFilterWhere';
			}

			switch ($column['data']) {
				case 'affiliate':
					$this->applyRelationFilter($query, $filterOperator, $value, 's.affiliate_id', [
						['LIKE', 'a.code'],
						['LIKE', 'au.first_name'],
						['LIKE', 'au.last_name'],
					]);
					break;
				case 'name':
					$query->$filterOperator([
						'OR',
						['LIKE', 'u.first_name', $value],
						['LIKE', 'u.last_name', $value],
					]);
					break;
				case 'subscriptions':
					// The select filter sends a subscription status (a subscriber matches when
					// one of its subscriptions has it); other text (global search) is a package.
					if ($value !== '') {
						$subscriptions = Subscription::find()
							->alias('ss')
							->where('[[ss.subscriber_id]] = [[s.id]]')
							->andWhere(['ss.deleted' => Subscription::NO]);
						if (is_numeric($value)) {
							$subscriptions->andWhere(['ss.status' => (int) $value]);
						} else {
							$subscriptions
								->innerJoin(['pt' => PackageTranslation::tableName()], '[[pt.package_id]] = [[ss.package_id]]')
								->andWhere(['LIKE', 'pt.name', $value]);
						}
						$query->$filterOperator(['exists', $subscriptions]);
					}
					break;
				case 'created_at':
					$query->$filterOperator(['LIKE', 's.created_at', DateHelper::formatAsDate($value)]);
					break;
				case 'email':
				case 'phone':
					$query->$filterOperator(['LIKE', 'u.' . $column['data'], $value]);
					break;
				default:
					break;
			}
		}
		return $query;
	}

	/**
	 * @inheritdoc
	 */
	public function applyOrder(ActiveQuery $query, $columns, $order)
	{
		foreach ($order as $item) {
			$column = $columns[$item['column']];
			if (array_key_exists('orderable', $column) && $column['orderable'] === 'false') {
				continue;
			}
			$sort = mb_strtolower($item['dir']) == 'desc' ? SORT_DESC : SORT_ASC;

			switch ($column['data']) {
				case 'affiliate':
					$query->addOrderBy(['a.code' => $sort]);
					break;
				case 'name':
					$query->addOrderBy(['u.first_name' => $sort, 'u.last_name' => $sort]);
					break;
				case 'email':
				case 'phone':
					$query->addOrderBy(['u.' . $column['data'] => $sort]);
					break;
				case 'created_at':
					$query->addOrderBy(['s.created_at' => $sort]);
					break;
				default:
					break;
			}
		}
		return $query;
	}
}
