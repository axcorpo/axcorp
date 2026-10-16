<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%menu_translation}}".
 *
 * @property int $menu_id
 * @property string $language_id
 * @property string $name
 * @property int $deleted
 *
 * @property Language $language
 * @property Menu $menu
 */
class MenuTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%menu_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['menu_id', 'language_id', 'name'], 'required'],
			[['menu_id', 'deleted'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['menu_id', 'language_id'], 'unique', 'targetAttribute' => ['menu_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['menu_id'], 'exist', 'skipOnError' => true, 'targetClass' => Menu::class, 'targetAttribute' => ['menu_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'menu_id' => Yii::t('label', 'Menu ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Title'),
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
	public function getMenu()
	{
		return $this->hasOne(Menu::class, ['id' => 'menu_id']);
	}
}
