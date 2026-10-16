<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%language_source}}".
 *
 * @property int $id
 * @property string $category
 * @property string $message
 *
 * @property LanguageTranslate[] $languageTranslates
 * @property Language[] $languages
 */
class LanguageSource extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%language_source}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id'], 'required'],
			[['id'], 'integer'],
			[['message'], 'string'],
			[['category'], 'string', 'max' => 32],
			[['id'], 'unique'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'category' => Yii::t('label', 'Category'),
			'message' => Yii::t('label', 'Message'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguageTranslates()
	{
		return $this->hasMany(LanguageTranslate::class, ['id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language'])->viaTable('{{%language_translate}}', ['id' => 'id']);
	}
}
