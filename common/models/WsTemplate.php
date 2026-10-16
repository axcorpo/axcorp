<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%ws_template}}".
 *
 * @property int $id
 * @property int $type
 * @property int $variant
 * @property int $section
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property WsTemplateTranslation[] $templateTranslations
 * @property WsTemplateTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class WsTemplate extends UuidActiveRecord
{
	const TYPE_EMAIL = 100;
	const TYPE_SMS = 101;
	const TYPE_ISSUED_DOCUMENT = 102;


	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%ws_template}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['type', 'status'], 'required'],
			[['type', 'variant', 'section', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'type' => Yii::t('label', 'Type'),
			'variant' => Yii::t('label', 'Variant'),
			'section' => Yii::t('label', 'Section'),
			'default' => Yii::t('label', 'Default'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTemplateTranslations()
	{
		return $this->hasMany(WsTemplateTranslation::class, ['template_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|TemplateTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->templateTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%ws_template_translation}}', ['template_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

    /**
     * Finds all records by ActivityDomain model ID(s).
     *
     * @param bool $asArray
     * @return array|static[]
     */
    public static function findAllForImport($asArray = true)
    {
        return static::find()
            ->alias('t')
            ->andWhere([
                't.status' => static::STATUS_ACTIVE,
                't.deleted' => static::NO,
            ])
            ->asArray($asArray)
            ->all();
    }
}
