<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%carousel_translation}}".
 *
 * @property int $carousel_id
 * @property string $language_id
 * @property string $name
 * @property int $deleted
 *
 * @property Carousel $carousel
 * @property Language $language
 */
class CarouselTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%carousel_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['carousel_id', 'language_id', 'name'], 'required'],
			[['carousel_id', 'deleted'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['carousel_id', 'language_id'], 'unique', 'targetAttribute' => ['carousel_id', 'language_id']],
			[['carousel_id'], 'exist', 'skipOnError' => true, 'targetClass' => Carousel::class, 'targetAttribute' => ['carousel_id' => 'id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'carousel_id' => Yii::t('label', 'Carousel ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
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
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}
}
