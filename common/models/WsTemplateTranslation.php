<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%ws_template_translation}}".
 *
 * @property int $template_id
 * @property string $language_id
 * @property string $name
 * @property string $subject
 * @property string $header
 * @property string $footer
 * @property string $content
 * @property string $secondary_content
 * @property string $tertiary_content
 * @property string $quaternary_content
 * @property int $deleted
 *
 * @property Language $language
 * @property WsTemplate $template
 */
class WsTemplateTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%ws_template_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['template_id', 'language_id', 'name'], 'required'],
			[['template_id', 'deleted'], 'integer'],
			[['header', 'footer', 'content', 'secondary_content', 'tertiary_content', 'quaternary_content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['name', 'subject'], 'string', 'max' => 255],
			[['template_id', 'language_id'], 'unique', 'targetAttribute' => ['template_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => WsTemplate::class, 'targetAttribute' => ['template_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'template_id' => Yii::t('label', 'Template ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
			'subject' => Yii::t('label', 'Subject'),
			'header' => Yii::t('label', 'Header'),
			'footer' => Yii::t('label', 'Footer'),
			'content' => Yii::t('label', 'Content'),
			'secondary_content' => Yii::t('label', 'Secondary Content'),
			'tertiary_content' => Yii::t('label', 'Tertiary Content'),
			'quaternary_content' => Yii::t('label', 'Quaternary Content'),
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
	public function getTemplate()
	{
		return $this->hasOne(WsTemplate::class, ['id' => 'template_id']);
	}

	/**
	 * Finds all records by Template model ID(s).
	 *
	 * @param int|int[] $template_id
	 * @param bool $asArray
	 * @return array|static[]
	 */
	public static function findAllByTemplate($template_id, $asArray = true)
	{
		return static::find()
			->andWhere([
				'template_id' => $template_id,
				'deleted' => static::NO,
			])
			->asArray($asArray)
			->all();
	}
}
