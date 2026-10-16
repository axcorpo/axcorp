<?php

namespace common\models;

use common\helpers\UploadHelper;
use DateTime;
use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%tutorial}}".
 *
 * @property int $id
 * @property string $image
 * @property string $file
 * @property int $type
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property TutorialCategoryHasTutorial[] $tutorialCategoryHasTutorials
 * @property TutorialCategory[] $tutorialCategories
 * @property TutorialTranslation[] $tutorialTranslations
 * @property TutorialTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 * @property string|null imageUrl
 * @property string|null fileUrl
 */
class Tutorial extends UuidActiveRecord
{
	const TYPE_VIDEO = 1;
	const TYPE_WRITTEN = 2;
	const TYPE_FILE = 3;

	/**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%tutorial}}';
    }

	/**
	 * @inheritdoc
	 * @throws \Exception
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
			],
		];
	}

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['created_at', 'updated_at'], 'default'],
            [['status'], 'required'],
            [['image', 'file'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('label', 'ID'),
            'image' => Yii::t('label', 'Image'),
            'file' => Yii::t('label', 'File'),
            'type' => Yii::t('label', 'Type'),
            'sort_order' => Yii::t('label', 'Sort Order'),
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
    public function getTutorialCategoryHasTutorials()
    {
        return $this->hasMany(TutorialCategoryHasTutorial::class, ['tutorial_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery|CommonActiveQuery
     */
    public function getTutorialCategories()
    {
        return $this->hasMany(TutorialCategory::class, ['id' => 'tutorial_category_id'])->viaTable('{{%tutorial_category_has_tutorial}}', ['tutorial_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTutorialTranslations()
    {
        return $this->hasMany(TutorialTranslation::class, ['tutorial_id' => 'id']);
    }

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return mixed
	 */
	public function getTranslation($language = null)
	{
		$language = $language ?: Yii::$app->language;
		return ArrayHelper::index($this->tutorialTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
    public function getLanguages()
    {
        return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%tutorial_translation}}', ['tutorial_id' => 'id']);
    }

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Gets the imageUrl with fallback to a blank image.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/tutorial/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Gets the fileUrl with fallback to a blank image.
	 *
	 * @return string
	 */
	public function getFileUrl()
	{
		return Url::to("@uploads/tutorial/{$this->uuid}/{$this->file}");
	}

	/**
	 * Model type array.
	 *
	 * @return array
	 */
	public static function getTypes()
	{
		return [
			self::TYPE_VIDEO => Yii::t('label', 'Video'),
			self::TYPE_WRITTEN => Yii::t('label', 'Written'),
			self::TYPE_FILE => Yii::t('label', 'File'),
		];
	}
}
