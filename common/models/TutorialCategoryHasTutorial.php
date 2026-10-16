<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%tutorial_category_has_tutorial}}".
 *
 * @property int $tutorial_category_id
 * @property int $tutorial_id
 *
 * @property Tutorial $tutorial
 * @property TutorialCategory $tutorialCategory
 */
class TutorialCategoryHasTutorial extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%tutorial_category_has_tutorial}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['tutorial_category_id', 'tutorial_id'], 'required'],
			[['tutorial_category_id', 'tutorial_id'], 'integer'],
			[['tutorial_category_id', 'tutorial_id'], 'unique', 'targetAttribute' => ['tutorial_category_id', 'tutorial_id']],
			[['tutorial_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tutorial::class, 'targetAttribute' => ['tutorial_id' => 'id']],
			[['tutorial_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => TutorialCategory::class, 'targetAttribute' => ['tutorial_category_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'tutorial_category_id' => Yii::t('label', 'Tutorial Category ID'),
			'tutorial_id' => Yii::t('label', 'Tutorial ID'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorial()
	{
		return $this->hasOne(Tutorial::class, ['id' => 'tutorial_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorialCategory()
	{
		return $this->hasOne(TutorialCategory::class, ['id' => 'tutorial_category_id']);
	}
}
