<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "{{%subscriber_status}}".
 *
 * @property int $id
 * @property int $status_category_id
 * @property string $icon
 * @property string $color
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property Subscriber[] $subscribers
 * @property SubscriberStatusCategory $subscriberStatusCategory
 * @property SubscriberStatusHasTemplate[] $subscriberStatusHasTemplates
 * @property Template[] $templates
 * @property SubscriberStatusTranslation[] $subscriberStatusTranslations
 * @property SubscriberStatusTranslation $translation
 * @property Language[] $languages
 * @property SubscriberHasStatus[] $subscriberHasStatuses
 * @property SubscriberDocument[] $subscriberDocuments
 * @property User $creator
 * @property User $updater
 */
class SubscriberStatus extends UuidActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%subscriber_status}}';
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function behaviors()
	{
		return [
			[
				'class' => BlameableBehavior::class,
			],
			[
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['status_category_id', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['icon', 'color'], 'string', 'max' => 255],
			[['status_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriberStatusCategory::class, 'targetAttribute' => ['status_category_id' => 'id']],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'status_category_id' => Yii::t('label', 'Status Category ID'),
			'icon' => Yii::t('label', 'Icon'),
			'color' => Yii::t('label', 'Color'),
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
    public function getSubscribers()
    {
        return $this->hasMany(Subscriber::class, ['id' => 'subscriber_id'])->viaTable('{{%subscriber_has_status}}', ['status_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery|CommonActiveQuery
     */
    public function getSubscriberDocuments()
    {
        return $this->hasMany(SubscriberDocument::class, ['status_id' => 'id']);
    }

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriberStatusCategory()
	{
		return $this->hasOne(SubscriberStatusCategory::class, ['id' => 'status_category_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriberHasTemplates()
	{
		return $this->hasMany(SubscriberStatusHasTemplate::class, ['status_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTemplates()
	{
		return $this->hasMany(Template::class, ['id' => 'template_id'])
			->andWhere(['type' => Template::TYPE_SUBSCRIBER])
			->viaTable('{{%subscriber_status_has_template}}', ['status_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriberStatusTranslations()
	{
		return $this->hasMany(SubscriberStatusTranslation::class, ['status_id' => 'id']);
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

		return ArrayHelper::index($this->subscriberStatusTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%subscriber_status_translation}}', ['status_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getSubscriberHasStatuses()
	{
		return $this->hasMany(SubscriberHasStatus::class, ['status_id' => 'id']);
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
	 * Gets the translated repair status name as HTML with icon and background color.
	 *
	 * @param string $tagName
	 * @param array $options
	 * @return string
	 */
	public function getFormattedName($tagName = 'span', $options = [], $name = null)
	{
        if (empty($name)) {
            $name = [$this->translation->name];
        }

		if ($this->icon) {
			array_unshift($name, Html::tag('span', null, ['class' => $this->icon]));
		}
		if ($this->color) {
			Html::addCssClass($options, ['label', 'label-shadow']);
			Html::addCssStyle($options, ['background-color' => $this->color]);
		}

		return Html::tag($tagName, implode(' ', $name), $options);
	}

	/**
	 * Finds all active records.
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public static function findAllStatuses()
	{
		return static::getDb()->cache(function ($db) {
			return static::find()
				->alias('s')
				->joinWith([
					'subscriberStatusTranslations st' => function (ActiveQuery $query) {
						$query->onCondition(['st.language_id' => Yii::$app->language]);
					},
					'subscriberStatusCategory.subscriberStatusCategoryTranslations sct' => function (ActiveQuery $query) {
						$query->onCondition(['sct.language_id' => Yii::$app->language]);
					},
				])
				->where([
					's.status' => self::STATUS_ACTIVE,
					's.deleted' => self::NO,
				])
				->orderBy(['st.name' => SORT_ASC])
				->all();
		}, 0, new TagDependency(['tags' => __FUNCTION__]));
	}

	/**
	 * Query all records by SubscriberCategory model id.
	 *
	 * @param $status_category_id
	 * @return array
	 */
	public static function queryStatusesByStatusCategoryId($status_category_id)
	{
		/** @var self[] $models */
		$models = static::find()
			->alias('s')
			->joinWith([
				'subscriberStatusTranslations st' => function (ActiveQuery $query) {
					$query->onCondition(['st.language_id' => Yii::$app->language]);
				},
				'subscriberStatusCategory.subscriberStatusCategoryTranslations sct' => function (ActiveQuery $query) {
					$query->onCondition(['sct.language_id' => Yii::$app->language]);
				},
			])
			->where([
				's.status' => self::STATUS_ACTIVE,
				's.deleted' => self::NO,
				's.status_category_id' => $status_category_id,
			])
			->orderBy(['st.name' => SORT_ASC])
			->all();

		$data = [];

		foreach ($models as $model) {
			$data[$model->subscriberStatusCategory->translation->name][] = [
				// The UUID string, never the raw BINARY(16) bytes (breaks JSON encoding)
				'id' => $model->uuid,
				'name' => $model->getFormattedName(),
			];
		}

		return $data;
	}
}
