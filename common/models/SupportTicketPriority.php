<?php

namespace common\models;

use tws\behaviors\DefaultBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%support_ticket_priority}}".
 *
 * @property int $id
 * @property int $sort_order
 * @property string $color
 * @property string $icon
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property SupportTicket[] $supportTickets
 * @property SupportTicketPriorityTranslation[] $supportTicketPriorityTranslations
 * @property SupportTicketPriorityTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class SupportTicketPriority extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%support_ticket_priority}}';
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
			],
			'DefaultBehavior' => [
				'class' => DefaultBehavior::class,
				'ensureDefaultValue' => true,
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
			[['sort_order', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['color', 'icon'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'sort_order' => Yii::t('label', 'Sort Order'),
			'color' => Yii::t('label', 'Color'),
			'icon' => Yii::t('label', 'Icon'),
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
	public function getSupportTickets()
	{
		return $this->hasMany(SupportTicket::class, ['support_ticket_priority_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketPriorityTranslations()
	{
		return $this->hasMany(SupportTicketPriorityTranslation::class, ['support_ticket_priority_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|SupportTicketPriorityTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->supportTicketPriorityTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%support_ticket_priority_translation}}', ['support_ticket_priority_id' => 'id']);
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
	 * Gets the translated status name as HTML with icon and background color.
	 *
	 * @param string $tagName
	 * @param array $options
	 * @return string
	 */
	public function getFormattedName($tagName = 'span', $options = [])
	{
		$name = [$this->translation->name];

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
	 * Finds the default record or fallback to the first record.
	 *
	 * @param bool $fallbackToFirst Indicates if the first record should be returned when a default one does not exist.
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultSupportTicketPriority($fallbackToFirst = true)
	{
		$query = static::find()
			->andWhere([
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->limit(1);

		if (!($model = $query->andWhere(['default' => self::YES])->one())) {
			if ($fallbackToFirst === true) {
				$model = $query->one();
			}
		}

		return $model;
	}

	/**
	 * Finds all support ticket priority models.
	 *
	 * @return array|\yii\db\ActiveRecord[]|self[]
	 */
	public static function findAllSupportTicketPriorities()
	{
		return static::find()
			->alias('stp')
			->joinWith([
				'supportTicketPriorityTranslations stpt',
			])
			->andWhere([
				'stp.status' => self::STATUS_ACTIVE,
				'stp.deleted' => self::NO,
			])
			->addOrderBy(new Expression('[[stp.sort_order]] IS NULL'))
			->addOrderBy(['stp.sort_order' => SORT_ASC])
			->all();
	}
}
