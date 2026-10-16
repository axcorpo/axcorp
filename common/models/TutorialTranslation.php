<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%tutorial_translation}}".
 *
 * @property int $tutorial_id
 * @property string $language_id
 * @property string $title
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property Tutorial $tutorial
 */
class TutorialTranslation extends UuidActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%tutorial_translation}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tutorial_id', 'language_id', 'title'], 'required'],
            [['tutorial_id', 'deleted'], 'integer'],
            [['content'], 'string'],
            [['language_id'], 'string', 'max' => 5],
            [['title'], 'string', 'max' => 255],
            [['tutorial_id', 'language_id'], 'unique', 'targetAttribute' => ['tutorial_id', 'language_id']],
            [['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
            [['tutorial_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tutorial::class, 'targetAttribute' => ['tutorial_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'tutorial_id' => Yii::t('label', 'Tutorial ID'),
            'language_id' => Yii::t('label', 'Language ID'),
            'title' => Yii::t('label', 'Title'),
            'content' => Yii::t('label', 'Content'),
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
    public function getTutorial()
    {
        return $this->hasOne(Tutorial::class, ['id' => 'tutorial_id']);
    }
}
