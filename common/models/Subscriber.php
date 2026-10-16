<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%subscriber}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $user_id
 * @property int $status_id
 * @property string $external_id
 * @property string $code
 * @property string $pin
 * @property string $date_of_birth
 * @property string $street_name
 * @property string $street_number
 * @property string $staircase
 * @property string $block
 * @property string $floor
 * @property string $apartment
 * @property string $locality
 * @property string $zip_code
 * @property string $county
 * @property string $country
 * @property string $notes
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 * @property string|null $affiliate_id set once at signup (AffiliateTracker), never mass-assigned
 *
 * @property Invoice[] $invoices
 * @property Affiliate|null $affiliate
 * @property AffiliateCommission[] $commissions
 * @property string $maskedName
 * @property string $maskedEmail
 * @property Subscriber $parent
 * @property Subscriber[] $subscribers
 * @property User $user
 * @property Subscription[] $subscriptions
 * @property User $creator
 * @property User $updater
 * @property string fullAddress
 */
class Subscriber extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%subscriber}}';
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
				'attributes' => ['date_of_birth'],
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
			[['parent_id', 'user_id', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['user_id', 'code', 'status'], 'required'],
			[['date_of_birth', 'created_at', 'updated_at'], 'safe'],
			[['date_of_birth', 'created_at', 'updated_at'], 'default'],
			[['external_id', 'code', 'pin', 'street_name', 'street_number', 'staircase', 'block', 'floor', 'apartment', 'locality', 'zip_code', 'county', 'country'], 'string', 'max' => 255],
			[['code'], 'unique'],
            [['notes'], 'string'],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscriber::class, 'targetAttribute' => ['parent_id' => 'id']],
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
			'parent_id' => Yii::t('label', 'Parent ID'),
			'user_id' => Yii::t('label', 'User ID'),
            'status_id' => Yii::t('label', 'Status ID'),
			'external_id' => Yii::t('label', 'External ID'),
			'code' => Yii::t('label', 'Code'),
			'pin' => Yii::t('label', 'Pin'),
			'date_of_birth' => Yii::t('label', 'Date Of Birth'),
			'street_name' => Yii::t('label', 'Street Name'),
			'street_number' => Yii::t('label', 'Street Number'),
			'address' => Yii::t('label', 'Address'),
			'staircase' => Yii::t('label', 'Staircase'),
			'block' => Yii::t('label', 'Block'),
			'floor' => Yii::t('label', 'Floor'),
			'apartment' => Yii::t('label', 'Apartment'),
			'locality' => Yii::t('label', 'Locality'),
			'zip_code' => Yii::t('label', 'Zip Code'),
			'county' => Yii::t('label', 'County'),
			'country' => Yii::t('label', 'Country'),
            'notes' => Yii::t('label', 'Notes'),
            'affiliate_id' => Yii::t('label', 'Affiliate'),
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
	public function getInvoices()
	{
		return $this->hasMany(Invoice::class, ['subscriber_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(Subscriber::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscribers()
	{
		return $this->hasMany(Subscriber::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUser()
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}

	/**
	 * The affiliate who brought this subscriber, if any.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getAffiliate()
	{
		return $this->hasOne(Affiliate::class, ['id' => 'affiliate_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCommissions()
	{
		return $this->hasMany(AffiliateCommission::class, ['subscriber_id' => 'id']);
	}

	/**
	 * The name as an affiliate may see it: first name and the last name's initial,
	 * e.g. "Ion P." (the affiliate never sees the full personal data — GDPR).
	 *
	 * @return string
	 */
	public function getMaskedName()
	{
		$user = $this->user;
		if ($user === null) {
			return '';
		}
		$last = trim((string) $user->last_name);
		return trim($user->first_name . ($last !== '' ? ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.' : ''));
	}

	/**
	 * The e-mail as an affiliate may see it, e.g. "i***@gmail.com".
	 *
	 * @return string
	 */
	public function getMaskedEmail()
	{
		$email = (string) ($this->user->email ?? '');
		if (($at = strpos($email, '@')) === false) {
			return $email === '' ? '' : mb_substr($email, 0, 1) . '***';
		}
		return mb_substr($email, 0, 1) . '***' . substr($email, $at);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptions()
	{
		return $this->hasMany(Subscription::class, ['subscriber_id' => 'id']);
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
	 * Returns all Workspace models that are related to this Subscriber model.
	 *
	 * @return array|\yii\db\ActiveRecord[]|Workspace[]
	 */
	public function getWorkspaces()
	{
		return Workspace::find()
			->alias('w')
			->joinWith([
				'subscription sub' => function (CommonActiveQuery $query) {
					$query->andWhere(['sub.subscriber_id' => $this->id]);
				},
			])
			->all();
	}

	/**
	 * Gets the fullAddress.
	 *
	 * @return string
	 */
	public function getFullAddress()
	{
		return implode(', ', array_filter([
			$this->address,
			$this->locality,
			$this->zip_code,
			$this->county,
			$this->country ? Country::findAllCountries()[$this->country]->translation->name : null,
		]));
	}

	/**
	 * Gets subscriber's free package subscription model.
	 *
	 * @return array|\yii\db\ActiveRecord|null|Subscription
	 */
	public function getFreePackageSubscription()
	{
		return $this->getSubscriptions()
			->andWhere([
				'type' => Subscription::TYPE_FREE,
				'deleted' => Subscription::NO,
			])
			->limit(1)
			->one();
	}

	/**
	 * Gets subscriber's shared subscription models.
	 *
	 * @return array|\yii\db\ActiveRecord[]|null|Subscription[]
	 */
	public function getSharedSubscriptions()
	{
		return $this->getSubscriptions()
			->andWhere(['IS NOT', 'parent_id', null])
			->andWhere([
				'deleted' => Subscription::NO,
			])
			->all();
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
	 * Model age category labels.
	 *
	 * @return array
	 */
	public static function getAgeCategoryLabels()
	{
		return [
			'18-' 	=> Yii::t('label', 'Under {item} years old', ['item' => 18]),
			'18-24' => Yii::t('label', 'Between {min} and {max} years old', ['min' => 18, 'max' => 24]),
			'25-34' => Yii::t('label', 'Between {min} and {max} years old', ['min' => 25, 'max' => 34]),
			'35-44' => Yii::t('label', 'Between {min} and {max} years old', ['min' => 35, 'max' => 44]),
			'45-54' => Yii::t('label', 'Between {min} and {max} years old', ['min' => 45, 'max' => 54]),
			'55-64' => Yii::t('label', 'Between {min} and {max} years old', ['min' => 55, 'max' => 64]),
			'65+' 	=> Yii::t('label', 'Over {item} years old', ['item' => 65]),
		];
	}

	/**
	 * Finds all active records.
	 *
	 * @return array|static[]
	 */
	public static function findAllSubscribers()
	{
		return static::find()
			->alias('ad')
			->joinWith([
				'user u',
			])
			->andWhere([
				'ad.status' => self::STATUS_ACTIVE,
				'ad.deleted' => self::NO,
			])
			->addOrderBy([
				'u.first_name' => SORT_ASC,
				'u.middle_name' => SORT_ASC,
				'u.last_name' => SORT_ASC,
			])
			->indexBy('id')
			->all();
	}
}
