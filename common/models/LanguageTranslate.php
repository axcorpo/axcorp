<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%language_translate}}".
 *
 * @property int $id
 * @property string $language
 * @property string $translation
 *
 * @property Language $language0
 * @property LanguageSource $id0
 */
class LanguageTranslate extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%language_translate}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id', 'language'], 'required'],
			[['id'], 'integer'],
			[['translation'], 'string'],
			[['language'], 'string', 'max' => 5],
			[['id', 'language'], 'unique', 'targetAttribute' => ['id', 'language']],
			[['language'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language' => 'language_id']],
			[['id'], 'exist', 'skipOnError' => true, 'targetClass' => LanguageSource::class, 'targetAttribute' => ['id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'language' => Yii::t('label', 'Language'),
			'translation' => Yii::t('label', 'Translation'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage0()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getId0()
	{
		return $this->hasOne(LanguageSource::class, ['id' => 'id']);
	}
}
