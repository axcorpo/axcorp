<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%unit_of_measure_code_translation}}".
 *
 * @property int $code_id
 * @property string $language_id
 * @property string $name
 * @property int $deleted
 *
 * @property Language $language
 * @property UnitOfMeasureCode $code
 */
class UnitOfMeasureCodeTranslation extends UuidActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%unit_of_measure_code_translation}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code_id', 'language_id', 'name'], 'required'],
            [['code_id', 'deleted'], 'integer'],
            [['language_id'], 'string', 'max' => 5],
            [['name'], 'string', 'max' => 255],
            [['code_id', 'language_id'], 'unique', 'targetAttribute' => ['code_id', 'language_id']],
            [['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
            [['code_id'], 'exist', 'skipOnError' => true, 'targetClass' => UnitOfMeasureCode::class, 'targetAttribute' => ['code_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'code_id' => Yii::t('label', 'Code ID'),
            'language_id' => Yii::t('label', 'Language ID'),
            'name' => Yii::t('label', 'Name'),
            'deleted' => Yii::t('label', 'Deleted'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLanguage()
    {
        return $this->hasOne(Language::class, ['language_id' => 'language_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCode()
    {
        return $this->hasOne(UnitOfMeasureCode::class, ['id' => 'code_id']);
    }
}
