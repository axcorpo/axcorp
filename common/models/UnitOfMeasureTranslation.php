<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%unit_of_measure_translation}}".
 *
 * @property int $unit_of_measure_id
 * @property string $language_id
 * @property string $name
 * @property string $symbol
 * @property int $deleted
 *
 * @property Language $language
 * @property UnitOfMeasure $unitOfMeasure
 */
class UnitOfMeasureTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%unit_of_measure_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['unit_of_measure_id', 'language_id', 'name', 'symbol'], 'required'],
			[['unit_of_measure_id', 'deleted'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['name', 'symbol'], 'string', 'max' => 255],
			[['unit_of_measure_id', 'language_id'], 'unique', 'targetAttribute' => ['unit_of_measure_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['unit_of_measure_id'], 'exist', 'skipOnError' => true, 'targetClass' => UnitOfMeasure::class, 'targetAttribute' => ['unit_of_measure_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'unit_of_measure_id' => Yii::t('label', 'Unit Of Measure ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
			'symbol' => Yii::t('label', 'Symbol'),
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
	public function getUnitOfMeasure()
	{
		return $this->hasOne(UnitOfMeasure::class, ['id' => 'unit_of_measure_id']);
	}
}
