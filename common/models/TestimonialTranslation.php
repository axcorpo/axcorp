<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%testimonial_translation}}".
 *
 * @property int $testimonial_id
 * @property string $language_id
 * @property string $message
 * @property string $role
 * @property int $deleted
 *
 * @property Language $language
 * @property Testimonial $testimonial
 */
class TestimonialTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%testimonial_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['testimonial_id', 'language_id', 'message'], 'required'],
			[['testimonial_id', 'deleted'], 'integer'],
			[['message'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['role'], 'string', 'max' => 255],
			[['testimonial_id', 'language_id'], 'unique', 'targetAttribute' => ['testimonial_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['testimonial_id'], 'exist', 'skipOnError' => true, 'targetClass' => Testimonial::class, 'targetAttribute' => ['testimonial_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'testimonial_id' => Yii::t('label', 'Testimonial ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'message' => Yii::t('label', 'Message'),
			'role' => Yii::t('label', 'Role'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTestimonial()
	{
		return $this->hasOne(Testimonial::class, ['id' => 'testimonial_id']);
	}
}
