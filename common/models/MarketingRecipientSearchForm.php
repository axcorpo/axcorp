<?php

namespace common\models;

use common\helpers\DateHelper;
use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;

/**
 * Class MarketingRecipientSearchForm
 *
 * @property ActiveQuery $query
 */
class MarketingRecipientSearchForm extends Model
{
	// User
	public $gender;
	public $first_name;
	public $last_name;

	// Subscriber
	public $date_of_birth;
	public $age;
	public $age_category;
	public $country;
	public $county;
	public $locality;

	/**
	 * @var \DateTime The current DateTime instance.
	 */
	public static $currentDate;

	/**
	 * @var ActiveQuery The search ActiveQuery.
	 */
	private $_query;


	/**
	 * @inheritdoc
	 */
	public function formName()
	{
		return 'filters';
	}

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		static::$currentDate = new \DateTime();
		$this->_query = MarketingRecipient::find()
			->alias('mr')
			->select([
				'mr.id',
				'mr.email',
				'mr.phone',
			])
			->joinWith([
				'user u',
				'user.subscriber s',
			])
			->andWhere([
				'mr.status' => MarketingRecipient::STATUS_ACTIVE,
				'mr.deleted' => MarketingRecipient::NO,
			])
			->groupBy(['mr.id']);
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			// User
			[['gender'], 'integer'],

			// Subscriber
			[['date_of_birth'], 'safe'],
			[['date_of_birth'], 'default'],
			[['age'], 'integer'],
            [['first_name', 'last_name'], 'string'],
			[['age_category'], 'each', 'rule' => ['string']],
			[['country'], 'each', 'rule' => ['exist', 'skipOnError' => true, 'targetClass' => Country::class, 'targetAttribute' => ['country' => 'iso_alpha2']]],
			[['county', 'locality'], 'string'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			// User
			'gender' => Yii::t('label', 'Gender'),
			'first_name' => Yii::t('label', 'First Name'),
			'last_name' => Yii::t('label', 'Last Name'),

			// Subscriber
			'date_of_birth' => Yii::t('label', 'Date Of Birth'),
			'age' => Yii::t('label', 'Age'),
			'age_category' => Yii::t('label', 'Age Category'),
			'country' => Yii::t('label', 'Country'),
			'county' => Yii::t('label', 'County'),
			'locality' => Yii::t('label', 'Locality'),
		];
	}

	/**
	 * Gets the search ActiveQuery.
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getQuery()
	{
		return $this->_query;
	}

	/**
	 * Applies User model filters.
	 */
	protected function applyUserFilters()
	{
		$this->_query->andFilterWhere([
			'AND',
			['=', 'u.gender', $this->gender],
			['LIKE', 'u.first_name', $this->first_name],
			['LIKE', 'u.last_name', $this->last_name],
		]);
	}

	/**
	 * Applies Subscriber model filters.
	 */
	protected function applySubscriberFilters()
	{
		$currentDate = static::$currentDate->format('Y-m-d H:i:s');
		$this->_query->addSelect(["TIMESTAMPDIFF(YEAR, [[s.date_of_birth]], '{$currentDate}') AS [[age]]"]);
		$this->_query->andFilterWhere([
			'AND',
			['=', 's.date_of_birth', DateHelper::formatAsDate($this->date_of_birth)],
			['IN', 's.country', $this->country],
			['LIKE', 's.county', $this->county],
			['LIKE', 's.locality', $this->locality],
		])
		->andFilterHaving(['=', 'age', $this->age]);

		foreach ((array) $this->age_category as $ageCategory) {
			$ageCategoryInterval = preg_split('/[\+,\-]/', $ageCategory);

			$this->_query->orFilterHaving(['BETWEEN', 'age', $ageCategoryInterval[0], $ageCategoryInterval[1]]);

			// Handle custom age categories
			if ($ageCategory == '18-') {
				$this->_query->orFilterHaving(['<', 'age', $ageCategoryInterval[0]]);
			} elseif ($ageCategory == '65+') {
				$this->_query->orFilterHaving(['>=', 'age', $ageCategoryInterval[0]]);
			}
		}
	}

	/**
	 * Searches the MarketingRecipient models.
	 *
	 * @return null|array|\yii\db\ActiveRecord[]|MarketingRecipient[]
	 */
	public function search()
	{
		$this->applyUserFilters();
		$this->applySubscriberFilters();

		return $this->getQuery()->indexBy('id')->all();
	}
}
