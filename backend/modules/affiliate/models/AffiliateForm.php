<?php

namespace backend\modules\affiliate\models;

use borales\extensions\phoneInput\PhoneInputValidator;
use common\models\Affiliate;
use common\models\User;
use common\traits\AccountEmailTrait;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Creates/updates an affiliate together with its master user account.
 *
 * A new affiliate gets a user created like SubscriberUserForm creates one (random
 * password, auth key, signup token + welcome/activation e-mails per the
 * `userAccountActivation` setting) but WITHOUT any RBAC assignment: every user holding a
 * role reaches the backend, an affiliate must not. When the e-mail already belongs to a
 * front-end account (no role, not an affiliate yet) that account is linked as is — its
 * data and password are left alone.
 *
 * Only the fields the form renders are assignable ([[scenarios()]]); `user_id`, `deleted`
 * and the blame/timestamp columns never come from the request.
 */
class AffiliateForm extends Affiliate
{
	use AccountEmailTrait;

	/**
	 * @var string
	 */
	public $email;

	/**
	 * @var string
	 */
	public $phone;

	/**
	 * @var int
	 */
	public $gender;

	/**
	 * @var string
	 */
	public $first_name;

	/**
	 * @var string
	 */
	public $middle_name;

	/**
	 * @var string
	 */
	public $last_name;

	/**
	 * @var User|null the account an existing front-end user was matched to on create
	 */
	private $_existingUser;

	/**
	 * @var bool whether save() linked an existing account instead of creating one
	 */
	public $linkedExistingUser = false;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return array_merge(parent::rules(), [
			[['email', 'phone', 'first_name', 'middle_name', 'last_name'], 'trim'],
			[['email', 'first_name', 'last_name'], 'required'],
			[['email', 'phone', 'first_name', 'middle_name', 'last_name'], 'string', 'max' => 255],
			[['email'], 'email'],
			[['phone'], PhoneInputValidator::class, 'skipOnEmpty' => true],
			[['gender'], 'integer'],
			[['gender'], 'in', 'range' => [User::GENDER_MALE, User::GENDER_FEMALE]],
			[['email'], 'validateEmailAccount'],
			[['phone'], 'validatePhoneAccount'],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'email' => Yii::t('label', 'Email'),
			'phone' => Yii::t('label', 'Phone'),
			'gender' => Yii::t('label', 'Gender'),
			'first_name' => Yii::t('label', 'First Name'),
			'middle_name' => Yii::t('label', 'Middle Name'),
			'last_name' => Yii::t('label', 'Last Name'),
		]);
	}

	/**
	 * Exactly the rendered fields — nothing else is mass-assignable.
	 *
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return [
			static::SCENARIO_DEFAULT => [
				'email', 'phone', 'gender', 'first_name', 'middle_name', 'last_name',
				'code', 'commission_percentage', 'commission_months', 'status', 'notes',
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		if ($user = $this->user) {
			$this->email = $user->email;
			$this->phone = $user->phone;
			$this->gender = $user->gender;
			$this->first_name = $user->first_name;
			$this->middle_name = $user->middle_name;
			$this->last_name = $user->last_name;
		}
	}

	/**
	 * The e-mail must not belong to a backend account (one with a role) nor to another
	 * affiliate; on create, a plain front-end account with it is linked instead of
	 * creating a duplicate (user.email is unique).
	 *
	 * @param string $attribute
	 */
	public function validateEmailAccount($attribute)
	{
		$user = User::find()->andWhere(['email' => $this->email])->one();
		if (!$user) {
			$this->_existingUser = null;
			return;
		}
		if (!$this->getIsNewRecord()) {
			if ($user->id != $this->user_id) {
				$this->addTakenError($attribute);
			}
			return;
		}
		if ($user->authAssignment) {
			$this->addError($attribute, Yii::t('error', 'This e-mail belongs to an administration account and cannot be used for an affiliate.'));
			return;
		}
		if (Affiliate::find()->andWhere(['user_id' => $user->id])->exists()) {
			$this->addError($attribute, Yii::t('error', 'This user is already an affiliate.'));
			return;
		}
		$this->_existingUser = $user;
	}

	/**
	 * @param string $attribute
	 */
	public function validatePhoneAccount($attribute)
	{
		if ($this->phone === null || $this->phone === '') {
			return;
		}
		// A linked account keeps its own phone; the typed one is not stored.
		if ($this->getIsNewRecord() && $this->_existingUser) {
			return;
		}
		$user = User::find()->andWhere(['phone' => $this->phone])->one();
		if ($user && ($this->getIsNewRecord() || $user->id != $this->user_id)) {
			$this->addTakenError($attribute);
		}
	}

	/**
	 * @param string $attribute
	 */
	protected function addTakenError($attribute)
	{
		$this->addError($attribute, Yii::t('yii', '{attribute} "{value}" has already been taken.', [
			'attribute' => $this->getAttributeLabel($attribute),
			'value' => $this->$attribute,
		]));
	}

	/**
	 * Saves the user account and the affiliate in one transaction.
	 *
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$isNewRecord = $this->getIsNewRecord();
		$createdUser = false;
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if ($runValidation && !$this->validate()) {
				throw new \Exception();
			}

			if ($isNewRecord && $this->_existingUser) {
				$user = $this->_existingUser;
				$this->linkedExistingUser = true;
			} elseif ($isNewRecord) {
				$user = $this->buildNewUser();
				$createdUser = true;
			} else {
				$user = $this->user;
				$this->applyUserAttributes($user);
			}
			if (!$this->linkedExistingUser && !$user->save()) {
				foreach ($user->getFirstErrors() as $attribute => $error) {
					$this->addError($this->hasProperty($attribute) ? $attribute : '', $error);
				}
				throw new \Exception();
			}

			$this->user_id = $user->id;
			// Validated above (scenario attributes); user_id was checked by validateEmailAccount().
			if (!parent::save(false, $attributeNames)) {
				throw new \Exception();
			}

			if ($createdUser) {
				$this->sendWelcomeEmail($user);
				if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION && !$this->sendActivationEmail($user)) {
					$this->addError('', Yii::t('common', 'Cannot send the account confirmation message.'));
					throw new \Exception();
				}
			}

			$dbTransaction->commit();
			return true;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			if ($isNewRecord) {
				$this->id = null;
				$this->user_id = null;
				$this->setIsNewRecord(true);
				$this->linkedExistingUser = false;
			}
			if ($e->getMessage() !== '') {
				Yii::error(['message' => 'Affiliate not saved.', 'error' => $e->getMessage()], __METHOD__);
				$this->addError('', Yii::t('common', 'Cannot save the record.'));
			}
			return false;
		}
	}

	/**
	 * A new user account like SubscriberUserForm creates for a new record: random
	 * password (the affiliate sets their own through activation / password reset), fresh
	 * auth key, signup token when accounts need confirmation. No role is assigned.
	 *
	 * @return User
	 * @throws \yii\base\Exception
	 */
	protected function buildNewUser()
	{
		$user = new User();
		$this->applyUserAttributes($user);
		$user->status = User::STATUS_ACTIVE;
		$user->setPassword(Yii::$app->security->generateRandomString(16));
		$user->generateAuthKey();
		if (Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_CONFIRMATION) {
			$user->status = User::STATUS_INACTIVE;
			$user->signup_token = User::generateSignupToken();
		}
		return $user;
	}

	/**
	 * @param User $user
	 */
	protected function applyUserAttributes(User $user)
	{
		$user->email = $this->email;
		$user->phone = $this->phone !== '' ? $this->phone : null;
		$user->gender = $this->gender !== '' ? $this->gender : null;
		$user->first_name = $this->first_name;
		$user->middle_name = $this->middle_name !== '' ? $this->middle_name : null;
		$user->last_name = $this->last_name;
	}
}
