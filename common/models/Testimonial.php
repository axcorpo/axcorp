<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%testimonial}}".
 *
 * @property int $id
 * @property string $image
 * @property string $name
 * @property string $organization
 * @property string $phone
 * @property string $email
 * @property string $rating
 * @property string $ip_address
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property TestimonialTranslation[] $testimonialTranslations
 * @property TestimonialTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 *
 * @property string|null $imageUrl
 */
class Testimonial extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%testimonial}}';
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
			[['rating'], 'number'],
			[['created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'name', 'organization', 'phone', 'email', 'ip_address'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'image' => Yii::t('label', 'Image'),
			'name' => Yii::t('label', 'Name'),
			'organization' => Yii::t('label', 'Organization'),
			'phone' => Yii::t('label', 'Phone'),
			'email' => Yii::t('label', 'Email'),
			'rating' => Yii::t('label', 'Rating'),
			'ip_address' => Yii::t('label', 'Ip Address'),
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
	public function getTestimonialTranslations()
	{
		return $this->hasMany(TestimonialTranslation::class, ['testimonial_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|TestimonialTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->testimonialTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%testimonial_translation}}', ['testimonial_id' => 'id']);
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
	 * Gets the imageUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/testimonial/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Finds the latest testimonials.
	 *
	 * @param null|int $limit The limit of the results.
	 * @return array|\yii\db\ActiveRecord|static[]|null
	 */
	public static function findLatestTestimonials($limit = null)
	{
		return static::find()
			->alias('t')
			->joinWith([
				'testimonialTranslations tt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'tt.language_id' => Yii::$app->language,
						'tt.deleted' => TestimonialTranslation::NO,
					]);
				},
			])
			->andWhere([
				't.status' => static::STATUS_ACTIVE,
				't.deleted' => static::NO,
			])
			->orderBy([
				't.created_at' => SORT_DESC,
			])
			->limit($limit)
			->all();
	}

	/**
	 * Provides active records.
	 *
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public static function provideTestimonials()
	{
		return static::find()
			->alias('t')
			->joinWith([
				'testimonialTranslations tt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'tt.language_id' => Yii::$app->language,
						'tt.deleted' => TestimonialTranslation::NO,
					]);
				},
			])
			->where([
				't.status' => static::STATUS_ACTIVE,
				't.deleted' => static::NO,
			])
			->orderBy([
				't.created_at' => SORT_DESC,
			]);
	}
}
