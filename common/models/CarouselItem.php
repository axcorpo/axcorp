<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%carousel_item}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $carousel_id
 * @property string $image
 * @property string $target
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Carousel $carousel
 * @property CarouselItem $parent
 * @property CarouselItem[] $carouselItems
 * @property CarouselItemTranslation[] $carouselItemTranslations
 * @property CarouselItemTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class CarouselItem extends UuidActiveRecord
{
	const TARGET_BLANK = '_blank';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%carousel_item}}';
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
				'groupAttributes' => ['carousel_id'],
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
			[['parent_id', 'carousel_id', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['carousel_id', 'status'], 'required'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['image', 'target'], 'string', 'max' => 255],
			[['carousel_id'], 'exist', 'skipOnError' => true, 'targetClass' => Carousel::class, 'targetAttribute' => ['carousel_id' => 'id']],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => CarouselItem::class, 'targetAttribute' => ['parent_id' => 'id']],
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
			'carousel_id' => Yii::t('label', 'Carousel ID'),
			'image' => Yii::t('label', 'Image'),
			'target' => Yii::t('label', 'Target'),
			'sort_order' => Yii::t('label', 'Sort Order'),
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
	public function getCarousel()
	{
		return $this->hasOne(Carousel::class, ['id' => 'carousel_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(CarouselItem::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCarouselItems()
	{
		return $this->hasMany(CarouselItem::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCarouselItemTranslations()
	{
		return $this->hasMany(CarouselItemTranslation::class, ['carousel_item_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return CarouselItemTranslation|array|null
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->carouselItemTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%carousel_item_translation}}', ['carousel_item_id' => 'id']);
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
		return Url::to("@uploads/carousel/{$this->uuidOf('carousel_id')}/{$this->image}", $scheme);
	}

	/**
	 * Model target labels.
	 *
	 * @return array
	 */
	public static function getTargetLabels()
	{
		return [
			static::TARGET_BLANK => Yii::t('label', 'New Window'),
		];
	}
}
