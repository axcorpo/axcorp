<?php

namespace common\models;

use DateTime;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%affiliate}}": a master user who earns a share of
 * the master invoices paid by the subscribers they referred (docs/afiliere.md). Affiliation
 * lives on master only: the stores (workspaces) take no part in it.
 *
 * Being an affiliate is this row, not an RBAC role — affiliates must not reach the
 * backend. Billing data and IBAN come from the user's existing `company`.
 *
 * `code` is what the referral links carry (`?aff=CODE`); AffiliateTracker normalizes it to
 * [A-Z0-9]{4,32}, so the same shape is enforced here. Changing the code does not move
 * subscribers already attributed (attribution is by subscriber.affiliate_id, fixed at
 * signup), but links with the old code stop attributing new ones.
 *
 * `commission_percentage` is copied onto each commission at computation time, so changing
 * it only affects invoices processed afterwards. `commission_months` NULL means lifetime.
 *
 * Financial history references the affiliate with ON DELETE RESTRICT: an affiliate with
 * commissions or payouts can only be soft-deleted (enableSoftDelete), never erased.
 *
 * A company applying from the public "become an affiliate" page gets this same row, in
 * STATUS_PENDING, with its `application_message`; the admin approves it (ACTIVE) or
 * rejects it (REJECTED + `rejection_reason`) through common\services\AffiliateApplicationService.
 * Neither a pending nor a rejected affiliate attributes signups, earns commissions or
 * reaches the affiliate area — all of those require STATUS_ACTIVE.
 *
 * @property string $id
 * @property string $user_id
 * @property string $code
 * @property string $commission_percentage
 * @property int|null $commission_months
 * @property string|null $notes
 * @property string|null $application_message
 * @property string|null $rejection_reason
 * @property string|null $reviewed_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property User $user
 * @property Company|null $company
 * @property AffiliateCommission[] $commissions
 * @property AffiliatePayout[] $payouts
 * @property Subscriber[] $subscribers
 * @property User $creator
 * @property User $updater
 */
class Affiliate extends UuidActiveRecord
{
	/** Applied from the public page, awaiting the admin's review. */
	const STATUS_PENDING = 2;
	/** Application rejected by the admin. */
	const STATUS_REJECTED = 3;

	/**
	 * Characters of generated codes: uppercase alphanumerics without the look-alikes
	 * (0/O, 1/I/L), so a code read aloud or retyped survives.
	 */
	const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%affiliate}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new DateTime)->format('Y-m-d H:i:s'),
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
			[['code'], 'filter', 'filter' => function ($value) {
				return is_string($value) ? strtoupper(trim($value)) : $value;
			}],
			[['user_id', 'code', 'commission_percentage', 'status'], 'required'],
			[['user_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['code'], 'match', 'pattern' => '/^[A-Z0-9]{4,32}$/', 'message' => Yii::t('error', '{attribute} must have 4 to 32 letters (A-Z) or digits.')],
			// Unique against deleted rows too: the DB key is, and a reused code would
			// silently re-attribute the old affiliate's links.
			[['code'], 'unique'],
			[['user_id'], 'unique', 'message' => Yii::t('error', 'This user is already an affiliate.')],
			[['commission_percentage'], 'number', 'min' => 0, 'max' => 100],
			[['commission_months'], 'integer', 'min' => 1],
			[['commission_months'], 'default', 'value' => null],
			[['notes', 'application_message', 'rejection_reason'], 'string'],
			[['status'], 'in', 'range' => array_keys(static::getStatusLabels())],
			[['created_at', 'updated_at', 'reviewed_at'], 'safe'],
			[['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'user_id' => Yii::t('label', 'User'),
			'code' => Yii::t('label', 'Affiliate Code'),
			'commission_percentage' => Yii::t('label', 'Commission Percentage'),
			'commission_months' => Yii::t('label', 'Commission Months'),
			'notes' => Yii::t('label', 'Notes'),
			'application_message' => Yii::t('label', 'Application Message'),
			'rejection_reason' => Yii::t('label', 'Rejection Reason'),
			'reviewed_at' => Yii::t('label', 'Reviewed At'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * The common active/inactive statuses plus the two of an application.
	 *
	 * {@inheritdoc}
	 */
	public static function getStatusLabels()
	{
		return parent::getStatusLabels() + [
			static::STATUS_PENDING => [
				'label' => Yii::t('label', 'Pending approval'),
				'color' => 'warning',
			],
			static::STATUS_REJECTED => [
				'label' => Yii::t('label', 'Rejected'),
				'color' => 'default',
			],
		];
	}

	/**
	 * Fills what a new affiliate needs and the admin did not set: a generated unique code
	 * and the `defaultPercentage` setting.
	 *
	 * {@inheritdoc}
	 */
	public function beforeValidate()
	{
		if ($this->isNewRecord) {
			$this->applyDefaults();
		}
		return parent::beforeValidate();
	}

	/**
	 * Sets the defaults of a new affiliate on the empty attributes only — call it before
	 * rendering the create form so the admin sees (and may change) the proposed values.
	 *
	 * @return $this
	 */
	public function applyDefaults()
	{
		if ($this->code === null || $this->code === '') {
			$this->code = static::generateUniqueCode();
		}
		if ($this->commission_percentage === null || $this->commission_percentage === '') {
			$this->commission_percentage = AffiliateSettings::getDefaultPercentage();
		}
		if ($this->status === null || $this->status === '') {
			$this->status = static::STATUS_ACTIVE;
		}
		return $this;
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	/**
	 * The affiliate's billing company (name, TIN, IBAN): the user's first non-deleted
	 * company, filled in through the existing account company pages.
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getCompany()
	{
		return $this->hasOne(Company::class, ['user_id' => 'user_id'])
			->andOnCondition([Company::tableName() . '.deleted' => Company::NO])
			->orderBy([Company::tableName() . '.created_at' => SORT_ASC]);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCommissions()
	{
		return $this->hasMany(AffiliateCommission::class, ['affiliate_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getPayouts()
	{
		return $this->hasMany(AffiliatePayout::class, ['affiliate_id' => 'id']);
	}

	/**
	 * The subscribers attributed to this affiliate (subscriber.affiliate_id, set at signup).
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getSubscribers()
	{
		return $this->hasMany(Subscriber::class, ['affiliate_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Whether the affiliate currently earns commissions and attributes new signups.
	 *
	 * @return bool
	 */
	public function getIsActive()
	{
		return (int) $this->status === static::STATUS_ACTIVE && (int) $this->deleted === static::NO;
	}

	/**
	 * Whether this is an application still awaiting the admin's decision.
	 *
	 * @return bool
	 */
	public function getIsPending()
	{
		return (int) $this->status === static::STATUS_PENDING && (int) $this->deleted === static::NO;
	}

	/**
	 * The referral link of this affiliate, ready to copy: a page of the master site with
	 * `?aff=CODE` (any master page captures it, see common\components\AffiliateTracker).
	 *
	 * @param string $path master frontend path; '' = the home page
	 * @return string
	 */
	public function getReferralUrl($path = '')
	{
		$host = Yii::$app->request instanceof \yii\web\Request
			? Yii::$app->request->hostInfo
			: (string) (Yii::$app->params['frontendUrl'] ?? '');
		return rtrim($host, '/') . '/' . ltrim($path, '/') . '?aff=' . rawurlencode($this->code);
	}

	/**
	 * Commission totals of this affiliate, per currency and status:
	 * `['RON' => [AffiliateCommission::STATUS_PENDING => '12.50', ...], ...]`.
	 * Cancelled commissions are left out. No currency conversion (decision: totals per
	 * currency).
	 *
	 * @return array
	 */
	public function getCommissionTotals()
	{
		$rows = $this->getCommissions()
			->select(['currency', 'status', 'total' => 'SUM([[amount]])'])
			->andWhere(['!=', 'status', AffiliateCommission::STATUS_CANCELLED])
			->groupBy(['currency', 'status'])
			->asArray()
			->all();

		$totals = [];
		foreach ($rows as $row) {
			$totals[$row['currency']][(int) $row['status']] = $row['total'];
		}
		return $totals;
	}

	/**
	 * Finds the active, non-deleted affiliate owning a referral code (case-insensitive,
	 * the tenants send it uppercased).
	 *
	 * @param string|null $code
	 * @return static|null
	 */
	public static function findActiveByCode($code)
	{
		if (!is_string($code) || trim($code) === '') {
			return null;
		}
		return static::find()
			->where([
				'code' => strtoupper(trim($code)),
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			])
			->one();
	}

	/**
	 * The affiliate row of a user, if any (deleted ones excluded).
	 *
	 * @param string $userId raw bytes or canonical uuid
	 * @return static|null
	 */
	public static function findByUserId($userId)
	{
		return static::find()
			->where(['user_id' => $userId, 'deleted' => static::NO])
			->one();
	}

	/**
	 * Generates a referral code no affiliate (deleted ones included) uses yet.
	 *
	 * @param int $length 4..32
	 * @return string
	 */
	public static function generateUniqueCode($length = 8)
	{
		$length = max(4, min(32, (int) $length));
		$alphabet = static::CODE_ALPHABET;
		$max = strlen($alphabet) - 1;
		do {
			$code = '';
			for ($i = 0; $i < $length; $i++) {
				$code .= $alphabet[random_int(0, $max)];
			}
		} while (static::find()->where(['code' => $code])->exists());

		return $code;
	}
}
