<?php

namespace common\models;

use Yii;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%country}}".
 *
 * @property int $id
 * @property string $iso_alpha2 Two-letter country code (ISO 3166-1 alpha-2)
 * @property string $iso_alpha3 Three-letter country code (ISO 3166-1 alpha-3)
 * @property string $iso_numeric Three-digit country number (ISO 3166-1 numeric)
 * @property string $name English country name
 * @property string $full_name Full English country name
 * @property string $original_name Original language name
 * @property string $continent_code
 * @property string $isd_code International Dialing Code
 * @property int $requires_postcode Is the postcode required when you are shipping parcel(s) to an address in the country
 * @property int $status
 * @property int $deleted
 *
 * @property CountryTranslation[] $countryTranslations
 * @property CountryTranslation $translation
 * @property Language[] $languages
 */
class Country extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%country}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['iso_alpha2', 'iso_alpha3', 'iso_numeric', 'name', 'full_name', 'continent_code', 'status'], 'required'],
			[['requires_postcode', 'status', 'deleted'], 'integer'],
			[['iso_alpha2', 'continent_code'], 'string', 'max' => 2],
			[['iso_alpha3', 'iso_numeric'], 'string', 'max' => 3],
			[['name', 'full_name', 'original_name'], 'string', 'max' => 255],
			[['isd_code'], 'string', 'max' => 7],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'iso_alpha2' => Yii::t('label', 'Iso Alpha2'),
			'iso_alpha3' => Yii::t('label', 'Iso Alpha3'),
			'iso_numeric' => Yii::t('label', 'Iso Numeric'),
			'name' => Yii::t('label', 'Name'),
			'full_name' => Yii::t('label', 'Full Name'),
			'original_name' => Yii::t('label', 'Original Name'),
			'continent_code' => Yii::t('label', 'Continent Code'),
			'isd_code' => Yii::t('label', 'Isd Code'),
			'requires_postcode' => Yii::t('label', 'Requires Postcode'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCountryTranslations()
	{
		return $this->hasMany(CountryTranslation::class, ['country_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return CountryTranslation|null
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->countryTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%country_translation}}', ['country_id' => 'id']);
	}

	/**
	 * Finds all active records.
	 *
	 * @return null|array|self[]
	 */
	public static function findAllCountries()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('c')
					->joinWith(['countryTranslations ct'])
					->andWhere([
						'c.status' => static::STATUS_ACTIVE,
						'c.deleted' => static::NO,
					])
					->orderBy(['c.name' => SORT_ASC])
					->indexBy('iso_alpha2')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}
}
