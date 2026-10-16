<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Expression;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%subscription}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $subscriber_id
 * @property int $package_id
 * @property int $company_id
 * @property string $external_id
 * @property string $code
 * @property int $trial_period
 * @property string $trial_cycle
 * @property int $billing_period
 * @property string $billing_cycle
 * @property string $start_at
 * @property string $end_at
 * @property string $price
 * @property string $currency
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property PaymentMetadata $paymentMetadata
 * @property Company $company
 * @property Package $package
 * @property Subscriber $subscriber
 * @property Subscription $parent
 * @property Subscription[] $subscriptions
 * @property SubscriptionFeature[] $subscriptionFeatures
 * @property SubscriptionHasUser[] $subscriptionHasUsers
 * @property User[] $invitedUsers
 * @property SupportTicket[] $supportTickets
 * @property Workspace[] $workspaces
 * @property Invoice[] $invoices
 * @property User $creator
 * @property User $updater
 *
 * @property string $formattedName
 */
class Subscription extends UuidActiveRecord
{
	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_SUSPENDED = 2;
	const STATUS_CANCELLED = 3;
	const STATUS_INCOMPLETE = 4;

	const TYPE_FREE = 1;
	const TYPE_STANDARD = 2;
	const TYPE_CUSTOM = 3;
	const TYPE_FEATURE = 9;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%subscription}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['start_at', 'end_at'],
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['parent_id', 'subscriber_id', 'package_id', 'company_id', 'trial_period', 'billing_period', 'type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['subscriber_id', 'code', 'status'], 'required'],
			[['start_at', 'end_at', 'created_at', 'updated_at'], 'safe'],
			[['start_at', 'end_at', 'created_at', 'updated_at'], 'default'],
			[['price'], 'number'],
			[['external_id', 'code', 'trial_cycle', 'billing_cycle'], 'string', 'max' => 255],
			[['currency'], 'string', 'max' => 3],
			[['code'], 'unique'],
			[['company_id'], 'exist', 'skipOnError' => true, 'targetClass' => Company::class, 'targetAttribute' => ['company_id' => 'id']],
			[['package_id'], 'exist', 'skipOnError' => true, 'targetClass' => Package::class, 'targetAttribute' => ['package_id' => 'id']],
			[['subscriber_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::class, 'targetAttribute' => ['subscriber_id' => 'id']],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'subscriber_id' => Yii::t('label', 'Subscriber ID'),
			'package_id' => Yii::t('label', 'Package ID'),
			'company_id' => Yii::t('label', 'Company ID'),
			'external_id' => Yii::t('label', 'External ID'),
			'code' => Yii::t('label', 'Code'),
			'trial_period' => Yii::t('label', 'Trial Period'),
			'trial_cycle' => Yii::t('label', 'Trial Cycle'),
			'billing_period' => Yii::t('label', 'Billing Period'),
			'billing_cycle' => Yii::t('label', 'Billing Cycle'),
			'start_at' => Yii::t('label', 'Start At'),
			'end_at' => Yii::t('label', 'End At'),
			'price' => Yii::t('label', 'Price'),
			'currency' => Yii::t('label', 'Currency'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPaymentMetadata()
	{
		return $this->hasOne(PaymentMetadata::class, ['subscription_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCompany()
	{
		return $this->hasOne(Company::class, ['id' => 'company_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPackage()
	{
		return $this->hasOne(Package::class, ['id' => 'package_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriber()
	{
		return $this->hasOne(Subscriber::class, ['id' => 'subscriber_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(Subscription::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptions()
	{
		return $this->hasMany(Subscription::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionFeatures()
	{
		return $this->hasMany(SubscriptionFeature::class, ['subscription_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionHasUsers()
	{
		return $this->hasMany(SubscriptionHasUser::class, ['subscription_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getInvitedUsers()
	{
		return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('{{%subscription_has_user}}', ['subscription_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTickets()
	{
		return $this->hasMany(SupportTicket::class, ['subscription_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspaces()
	{
		return $this->hasMany(Workspace::class, ['subscription_id' => 'id']);
	}

	/**
	 * Gets the related invoice items.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getItems()
	{
		return $this->hasMany(Item::class, ['item_id' => 'id']);
	}

	/**
	 * Gets the related invoices.
	 *
	 * @param bool $eagerLoadItems flag that indicates if the invoice items should be eager loaded.
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getInvoices($eagerLoadItems = true)
	{
		return Invoice::find()
			->alias('i')
			->joinWith([
				'items itm' => function (ActiveQuery $query) {
					$query->andWhere([
						'itm.item_id' => $this->id,
						'itm.type' => Item::TYPE_SUBSCRIPTION,
					]);
				},
			], $eagerLoadItems);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Gets the SubscriptionFeature model by its name property.
	 *
	 * @param string $feature The feature name.
	 * @return \yii\db\ActiveRecord|SubscriptionFeature|null
	 */
	public function getSubscriptionFeatureByName($feature)
	{
		$subscriptionQuery = Subscription::find()
			->select(['id'])
			->andWhere([
				'OR',
				['=', 'id', $this->id],
				['=', 'parent_id', $this->id],
			])
			->andWhere(['status' => Subscription::STATUS_ACTIVE]);

		$condition = [
			'sf.subscription_id' => $subscriptionQuery,
			'sf.name' => $feature,
			'sf.deleted' => SubscriptionFeature::NO,
		];

		// The feature is spread over the subscription and its children and the caller
		// wants their values added up. That used to be `SELECT sf.*, SUM(sf.value) ...
		// GROUP BY sf.name` in one go, which MySQL rejects under only_full_group_by
		// (its default): sf.* is neither grouped nor aggregated, so the check behind
		// "can this subscription hold another workspace" died with a 1055. The oldest
		// row carries the identity now and the sum is
		// fetched separately over the same rows; populateRecord() writes the old
		// attributes too, so the result behaves like the record the grouped query
		// returned — a read-only view whose `value` is the total, not a dirty model.
		$row = SubscriptionFeature::find()
			->alias('sf')
			->andWhere($condition)
			->orderBy(['sf.id' => SORT_ASC])
			->asArray()
			->one();

		if ($row === null) {
			return null;
		}

		$row['value'] = SubscriptionFeature::find()
			->alias('sf')
			->andWhere($condition)
			->sum('sf.value');

		$model = new SubscriptionFeature();
		SubscriptionFeature::populateRecord($model, $row);

		return $model;
	}

	/**
	 * Gets the formatted name.
	 *
	 * @param bool $asHtml Flag that indicates if HTML should be included in the result.
	 * @return string
	 */
	public function getFormattedName($asHtml = false)
	{
		$name = ["#{$this->code}"];
		if ($asHtml) {
			if ($this->type == static::TYPE_FEATURE) {
				$name[] = \yii\helpers\Html::tag('span', null, ['class' => 'fa fa-puzzle-piece']);
			}
		}
		$name[] = "({$this->package->translation->name})";

		return implode(' ', $name);
	}

	/**
	 * Gets the formatted billing cycle.
	 *
	 * @return string
	 */
	public function getFormattedBillingCycle()
	{
		return self::formatBillingCycle($this->billing_period, $this->billing_cycle);
	}

	/**
	 * Gets the end interval DateTime instance.
	 *
	 * @param null|string $start_at A custom start date against the end date will be computed.
	 * @return \DateTime|null
	 */
	public function getEndAtDate($start_at = null)
	{
		try {
			$billingPeriod = $this->billing_period ?: $this->package->billing_period;
			$billingCycle = $this->billing_cycle ?: $this->package->billing_cycle;

			return (new \DateTime($start_at ?: $this->start_at))->modify("+{$billingPeriod} {$billingCycle}");
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Gets the trial end interval DateTime instance.
	 *
	 * @param null|string $start_at A custom start date against the trial end date will be computed.
	 * @return \DateTime|null
	 */
	public function getTrialEndAtDate($start_at = null)
	{
		try {
			$trialPeriod = $this->trial_period ?: $this->package->trial_period;
			$trialCycle = $this->trial_cycle ?: $this->package->trial_cycle;

			return (new \DateTime($start_at ?: $this->start_at))->modify("+{$trialPeriod} {$trialCycle}");
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Checks if Subscription in on trial period.
	 *
	 * @return bool
	 */
	public function getIsOnTrialPeriod()
	{
		try {
			return !empty($this->trial_period) && !empty($this->trial_cycle) && ($this->getTrialEndAtDate() > new \DateTime());
		} catch (\Exception $e) {
			return false;
		}
	}

    /**
     * Gets the client details.
     *
     * @return array
     */
    public function getPaymentDetails()
    {
        return [
            'payment_method' => $this->paymentMetadata->payment_method ?: PaymentMetadata::PAYMENT_METHOD_BANK,
            'payment_processor' => $this->paymentMetadata->payment_processor,
        ];
    }



	/**
	 * Deactivates the subscription.
	 *
	 * @todo update all children subscriptions?
	 * @return bool
	 */
	public function deactivate()
	{
		try {
			$this->status = self::STATUS_INACTIVE;
			$this->end_at = (new \DateTime)->format('Y-m-d H:i:s');

			if (!$this->save()) {
				throw new \Exception('Cannot save the subscription: ' . print_r($this->errors, true));
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'subscription' => $this->code], __METHOD__);
			return false;
		}
	}

	/**
	 * Whether the subscription runs on a billing cycle (its own, or its package's).
	 *
	 * A paid subscription always does. A free one does only when its package was given
	 * a cycle: it is then a term subscription that expires at end_at without renewal,
	 * otherwise it is lifetime (end_at NULL).
	 *
	 * @return bool
	 */
	public function getHasBillingCycle()
	{
		return !empty($this->billing_period ?: ($this->package->billing_period ?? null))
			&& !empty($this->billing_cycle ?: ($this->package->billing_cycle ?? null));
	}

	/**
	 * Activates the subscription.
	 * Each time this method is called it will overwrite the start_at and end_at attributes.
	 *
	 * @param string $start_at
	 * @param bool $trial flag that indicates if the subscriptions should be activated as trial.
	 * @return bool
	 * @todo update all children subscriptions?
	 */
	public function activate($start_at = 'now', $trial = false, $end_at = null)
	{
		try {
			$startAt = new \DateTime($start_at);

			if ($this->type == self::TYPE_FREE) {
			    if (empty($end_at)) {
                    $this->start_at = $startAt->format('Y-m-d H:i:s');
                    // Term when the package has a cycle, lifetime otherwise.
                    $this->end_at = $this->getHasBillingCycle() ? $this->getEndAtDate()->format('Y-m-d H:i:s') : null;
                } else {
                    $this->start_at = $startAt->format('Y-m-d H:i:s');
                    $this->end_at = $end_at;
                }
			} else {
				$this->start_at = $startAt->format('Y-m-d H:i:s');
				if ($trial === true) {
				    if (empty($end_at)) {
                        $this->end_at = $this->getTrialEndAtDate()->format('Y-m-d H:i:s');
                    } else {
                        $this->end_at = $end_at;
                    }
				} else {
				    if (empty($end_at)) {
                        $this->end_at = $this->getEndAtDate()->format('Y-m-d H:i:s');
                        $this->trial_period = null;
                        $this->trial_cycle = null;
                    } else {
                        $this->end_at = $end_at;
                        $this->trial_period = null;
                        $this->trial_cycle = null;
                    }
				}
			}
			$this->status = self::STATUS_ACTIVE;

			if (!$this->save()) {
				throw new \Exception('Cannot save the subscription: ' . print_r($this->errors, true));
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'subscription' => $this->code], __METHOD__);
			return false;
		}
	}

	/**
	 * Suspends the subscription.
	 *
	 * @todo update all children subscriptions?
	 * @return bool
	 */
	public function suspend()
	{
		try {
			$this->status = self::STATUS_SUSPENDED;

			if (!$this->save()) {
				throw new \Exception('Cannot save the subscription: ' . print_r($this->errors, true));
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'subscription' => $this->code], __METHOD__);
			return false;
		}
	}

	/**
	 * Cancels the subscription.
	 *
	 * @todo update all children subscriptions?
	 * @return bool
	 */
	public function cancel()
	{
		try {
			$this->status = self::STATUS_CANCELLED;

			if (!$this->save()) {
				throw new \Exception('Cannot save the subscription: ' . print_r($this->errors, true));
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'subscription' => $this->code], __METHOD__);
			return false;
		}
	}

	/**
	 * Reactivates the subscription.
	 *
	 * @todo update all children subscriptions?
	 * @return bool
	 */
	public function reactivate()
	{
		try {
			$this->status = self::STATUS_ACTIVE;

			if (!$this->save()) {
				throw new \Exception('Cannot save the subscription: ' . print_r($this->errors, true));
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'subscription' => $this->code], __METHOD__);
			return false;
		}
	}

	/**
	 * Generates an unique code.
	 *
	 * @param int $length
	 * @return string
	 * @throws \yii\base\Exception
	 */
	public static function generateUniqueCode($length = 8)
	{
		$code = Yii::$app->security->generateRandomString($length);

		// Ensure that the generated string is alphanumeric
		if (!preg_match('/^[a-zA-Z0-9]*$/', $code)) {
			return static::generateUniqueCode($length);
		}

		// Ensure that the generated string is unique
		if (static::find()->where(['code' => $code])->limit(1)->exists()) {
			return static::generateUniqueCode($length);
		}

		return $code;
	}

	/**
	 * Model status labels.
	 *
	 * @return array
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_INACTIVE => [
				'label' => Yii::t('label', 'Inactive'),
				'color' => 'danger',
				'hexColor' => '#f55060',
			],
			self::STATUS_ACTIVE => [
				'label' => Yii::t('label', 'Active'),
				'color' => 'success',
				'hexColor' => '#5cb85c',
			],
			self::STATUS_SUSPENDED => [
				'label' => Yii::t('label', 'Suspended'),
				'color' => 'warning',
				'hexColor' => '#f0ad4e',
			],
			self::STATUS_CANCELLED => [
				'label' => Yii::t('label', 'Cancelled'),
				'color' => 'danger',
				'hexColor' => '#5bc0de',
			],
			self::STATUS_INCOMPLETE => [
				'label' => Yii::t('label', 'Incomplete'),
				'color' => 'primary',
				'hexColor' => '#337ab7',
			],
		];
	}

	/**
	 * Model cycle labels.
	 *
	 * @param null|string $mode
	 * @return array
	 */
	public static function getCycleLabels($mode = null)
	{
		$cycleLabels = ScheduledTask::getCycleLabels($mode);
		unset(
			$cycleLabels[ScheduledTask::CYCLE_MINUTE],
			$cycleLabels[ScheduledTask::CYCLE_HOUR]
		);
		return $cycleLabels;
	}

	/**
	 * Formats the billing cycle text.
	 *
	 * @param int $period
	 * @param string $cycle
	 * @return string
	 */
	public static function formatBillingCycle($period, $cycle)
	{
		$str = '';

		if ($period == 1) {
			$str = Yii::t('common', 'In each {0}', [mb_strtolower(self::getCycleLabels()[$cycle])]);
		} elseif ($period > 1) {
			$str = Yii::t('common', 'At each {0} {1}', [$period, mb_strtolower(self::getCycleLabels('plural')[$cycle])]);
		}

		return $str;
	}

	/**
	 * Gets the subscriptions that are available.
	 *
	 * @param null $subscriber_id
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findAvailableSubscriptions($subscriber_id = null, $isNewRecord = false)
	{
		try {
			$currentDate = (new \DateTime)->format('Y-m-d H:i:s');
            $subscriptionFeatureQuery = SubscriptionFeature::find()
                ->alias('sf')
                ->select([
                    'sf.subscription_id',
                    'SUM([[sf.value]]) AS [[value]]',
                ])
                ->andWhere([
                    'sf.name' => Feature::WORKSPACES,
                    'sf.deleted' => static::NO,
                ])
                ->groupBy([
                    'sf.name',
                    'sf.subscription_id',
                ]);
            $workspaceQuery = Workspace::find()
                ->alias('w')
                ->select([
                    'w.subscription_id',
                    'COUNT(*) AS counter',
                ])
                ->where([
                    'w.deleted' => static::NO,
                    'w.status' => static::STATUS_ACTIVE,
                ])
                ->groupBy([
                    'w.id',
                ]);
			$subscriptionsQuery = Subscription::find()
				->alias('s')
				->joinWith([
					'package p',
					'package.packageTranslations pt' => function (ActiveQuery $query) {
						$query->andOnCondition([
							'pt.language_id' => Yii::$app->language,
							'pt.deleted' => static::NO,
						]);
					},
				])
                ->leftJoin(['wq' => $workspaceQuery], '[[s.id]] = [[wq.subscription_id]]')
                ->leftJoin(['sfq' => $subscriptionFeatureQuery], '[[s.id]] = [[sfq.subscription_id]]')
				->andWhere([
					's.deleted' => static::NO,
				]);
            if ($subscriber_id) {
                $subscriptionsQuery->andWhere([
                    'AND',
                    ['IS NOT', 's.subscriber_id', null],
                    ['=', 's.subscriber_id', $subscriber_id],
                ]);
            }
            if ($isNewRecord) {
                $subscriptionsQuery->andWhere([
                    's.status' => static::STATUS_ACTIVE,
                ])
                ->andWhere([
                    'AND',
                    ['IS', 's.parent_id', null],
                    ['!=', 's.type', static::TYPE_FREE],
                ])
                ->andWhere(new Expression("[[sfq.value]] > 0"))
                ->andWhere(new Expression("[[wq.counter]] IS NULL OR ([[wq.counter]] < [[sfq.value]])"))
                ->andWhere(new Expression("TIMESTAMPDIFF(SECOND, '{$currentDate}', [[s.end_at]]) > 0"));
            }
			return $subscriptionsQuery->all();
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * Finds all records by Subscriber model id.
	 *
	 * @param int $subscriber_id
	 * @return array|\yii\db\ActiveRecord[]|static[]
	 */
	public static function findSubscriptionsBySubscriber($subscriber_id)
	{
		return Subscription::find()
			->alias('sub')
			->joinWith([
				'package p',
				'package.packageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PackageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'sub.subscriber_id' => $subscriber_id,
				'sub.status' => static::STATUS_ACTIVE,
				'sub.deleted' => static::NO,
			])
			->all();
	}
}
