<?php

namespace common\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%language}}".
 *
 * @property string $language_id
 * @property string $language
 * @property string $country
 * @property string $name
 * @property string $name_ascii
 * @property int $status
 *
 * @property LanguageTranslate[] $languageTranslates
 * @property LanguageSource[] $ids
 */
class Language extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%language}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['language_id', 'language', 'country', 'name', 'name_ascii', 'status'], 'required'],
			[['status'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['language', 'country'], 'string', 'max' => 3],
			[['name', 'name_ascii'], 'string', 'max' => 32],
			[['language_id'], 'unique'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'language_id' => Yii::t('label', 'Language ID'),
			'language' => Yii::t('label', 'Language'),
			'country' => Yii::t('label', 'Country'),
			'name' => Yii::t('label', 'Name'),
			'name_ascii' => Yii::t('label', 'Name Ascii'),
			'status' => Yii::t('label', 'Status'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguageTranslates()
	{
		return $this->hasMany(LanguageTranslate::class, ['language' => 'language_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getIds()
	{
		return $this->hasMany(LanguageSource::class, ['id' => 'id'])->viaTable('{{%language_translate}}', ['language' => 'language_id']);
	}

	/**
	 * Finds all active records.
	 *
	 * @return null|self[]
	 */
	public static function findAllLanguages()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->where([
						'status' => static::STATUS_ACTIVE,
					])
					->orderBy(['name' => SORT_ASC])
					->indexBy('language_id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}
}
