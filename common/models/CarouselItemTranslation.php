<?php

namespace common\models;

use common\helpers\HtmlSanitizer;
use Yii;

/**
 * This is the model class for table "{{%carousel_item_translation}}".
 *
 * @property int $carousel_item_id
 * @property string $language_id
 * @property string $title
 * @property string $content
 * @property string $anchor
 * @property string $url
 * @property int $deleted
 *
 * @property CarouselItem $carouselItem
 * @property Language $language
 */
class CarouselItemTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%carousel_item_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['carousel_item_id', 'language_id'], 'required'],
			[['carousel_item_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['title', 'anchor', 'url'], 'string', 'max' => 255],
			[['carousel_item_id', 'language_id'], 'unique', 'targetAttribute' => ['carousel_item_id', 'language_id']],
			[['carousel_item_id'], 'exist', 'skipOnError' => true, 'targetClass' => CarouselItem::class, 'targetAttribute' => ['carousel_item_id' => 'id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'carousel_item_id' => Yii::t('label', 'Carousel Item ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'title' => Yii::t('label', 'Title'),
			'content' => Yii::t('label', 'Content'),
			'anchor' => Yii::t('label', 'Anchor'),
			'url' => Yii::t('label', 'URL'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * The title carries the slide's line breaks (`<br>`), so it is not reduced to plain
	 * text like the other short columns; it gets the website allow-list instead.
	 */
	protected function selfSanitizedAttributes()
	{
		return ['title'];
	}

	/**
	 * {@inheritdoc}
	 *
	 * The title and the content are printed as HTML on the public homepage, so they are
	 * reduced to the website allow-list before they are stored; the homepage applies it
	 * again on the way out for what was stored before.
	 */
	public function beforeSave($insert)
	{
		if (!parent::beforeSave($insert)) {
			return false;
		}
		if (is_string($this->title) && strpos($this->title, '<') !== false) {
			$this->title = HtmlSanitizer::websiteContent($this->title);
		}
		$this->content = HtmlSanitizer::websiteContent($this->content);

		return true;
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCarouselItem()
	{
		return $this->hasOne(CarouselItem::class, ['id' => 'carousel_item_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}
}
