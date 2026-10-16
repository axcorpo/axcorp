<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%menu_item_translation}}".
 *
 * @property int $menu_item_id
 * @property string $language_id
 * @property string $title
 * @property string $url
 * @property string $slug
 *
 * @property Language $language
 * @property MenuItem $menuItem
 */
class MenuItemTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%menu_item_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['menu_item_id', 'language_id'], 'required'],
			[['menu_item_id'], 'integer'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'url', 'slug'], 'string', 'max' => 255],
			[['menu_item_id', 'language_id'], 'unique', 'targetAttribute' => ['menu_item_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['menu_item_id'], 'exist', 'skipOnError' => true, 'targetClass' => MenuItem::class, 'targetAttribute' => ['menu_item_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'menu_item_id' => Yii::t('label', 'Menu Item ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'title' => Yii::t('label', 'Title'),
			'url' => Yii::t('label', 'URL'),
			'slug' => Yii::t('label', 'Slug'),
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
	public function getMenuItem()
	{
		return $this->hasOne(MenuItem::class, ['id' => 'menu_item_id']);
	}
}
