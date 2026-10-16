<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%survey_translation}}".
 *
 * @property int $survey_id
 * @property string $language_id
 * @property string $name
 * @property int $deleted
 *
 * @property Language $language
 * @property Survey $survey
 */
class SurveyTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%survey_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['survey_id', 'language_id', 'name'], 'required'],
			[['survey_id', 'deleted'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['survey_id', 'language_id'], 'unique', 'targetAttribute' => ['survey_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['survey_id'], 'exist', 'skipOnError' => true, 'targetClass' => Survey::class, 'targetAttribute' => ['survey_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'survey_id' => Yii::t('label', 'Survey ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
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
	public function getSurvey()
	{
		return $this->hasOne(Survey::class, ['id' => 'survey_id']);
	}
}
