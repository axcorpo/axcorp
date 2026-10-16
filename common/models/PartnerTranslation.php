<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%partner_translation}}".
 *
 * @property int $partner_id
 * @property string $language_id
 * @property string $description
 * @property int $deleted
 *
 * @property Language $language
 * @property Partner $partner
 */
class PartnerTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%partner_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['partner_id', 'language_id'], 'required'],
			[['partner_id', 'deleted'], 'integer'],
			[['description'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['partner_id', 'language_id'], 'unique', 'targetAttribute' => ['partner_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['partner_id'], 'exist', 'skipOnError' => true, 'targetClass' => Partner::class, 'targetAttribute' => ['partner_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'partner_id' => Yii::t('label', 'Partner ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'description' => Yii::t('label', 'Description'),
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
	public function getPartner()
	{
		return $this->hasOne(Partner::class, ['id' => 'partner_id']);
	}
}
