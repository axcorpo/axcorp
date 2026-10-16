<?php

namespace common\models;

use Cron\CronExpression;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%scheduled_task}}".
 *
 * @property int $id
 * @property string $cron_expression
 * @property string $app_command
 * @property string $shell_command
 * @property string $request_url
 * @property string $request_config
 * @property string $resource
 * @property string $resource_key
 * @property string $application
 * @property int $type
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property User $creator
 * @property User $updater
 */
class ScheduledTask extends UuidActiveRecord
{
	const TYPE_APP = 1;
	const TYPE_USER = 2;

	const CYCLE_MINUTE = 'minute';
	const CYCLE_HOUR = 'hour';
	const CYCLE_DAY = 'day';
	const CYCLE_WEEK = 'week';
	const CYCLE_MONTH = 'month';
	const CYCLE_YEAR = 'year';

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%scheduled_task}}';
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
			[['app_command', 'shell_command', 'request_config'], 'string'],
			[['type', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['cron_expression', 'request_url', 'resource', 'resource_key', 'application'], 'string', 'max' => 255],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'cron_expression' => Yii::t('label', 'Cron Expression'),
			'app_command' => Yii::t('label', 'App Command'),
			'shell_command' => Yii::t('label', 'Shell Command'),
			'request_url' => Yii::t('label', 'Request Url'),
			'request_config' => Yii::t('label', 'Request Config'),
			'resource' => Yii::t('label', 'Resource'),
			'resource_key' => Yii::t('label', 'Resource Key'),
			'application' => Yii::t('label', 'Application'),
			'type' => Yii::t('label', 'Type'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeValidate()
	{
		if (isset($this->resource_key)) {
			// resource_key is a VARCHAR polymorphic reference; when a caller assigns a raw
			// BINARY(16) id, store its canonical UUID string instead of invalid UTF-8 bytes.
			if (\common\helpers\UuidHelper::isBytes($this->resource_key) && !mb_check_encoding($this->resource_key, 'UTF-8')) {
				$this->resource_key = \common\helpers\UuidHelper::fromBytes($this->resource_key);
			} else {
				$this->resource_key = (string) $this->resource_key;
			}
		}
		return parent::beforeValidate();
	}

	/**
	 * @inheritdoc
	 * Overwrites the parent implementation to ensure a different default argument value.
	 */
	public function delete($isPermanent = true)
	{
		return parent::delete($isPermanent);
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
	 * Enables the scheduled task.
	 *
	 * @return bool
	 */
	public function enable()
	{
		$this->status = self::STATUS_ACTIVE;
		return $this->save();
	}

	/**
	 * Disables the scheduled task.
	 *
	 * @return bool
	 */
	public function disable()
	{
		$this->status = self::STATUS_INACTIVE;
		return $this->save();
	}

	/**
	 * Gets the next due date(s).
	 *
	 * @param int $total The total number of next run dates to be returned.
	 * @param string $relativeDate Relative calculation date.
	 * @return \DateTime|\DateTime[]|array
	 */
	public function getNextDueDate($total = 1, $relativeDate = 'now')
	{
		if ($total === 1) {
			return CronExpression::factory($this->cron_expression)->getNextRunDate($relativeDate);
		}
		return CronExpression::factory($this->cron_expression)->getMultipleRunDates($total, $relativeDate);
	}

	/**
	 * Model cycle labels.
	 *
	 * @param string|null $mode The labels mode.
	 * @return array
	 */
	public static function getCycleLabels($mode = null)
	{
		if ($mode === 'plural') {
			return [
				self::CYCLE_MINUTE => Yii::t('label', 'Minutes'),
				self::CYCLE_HOUR => Yii::t('label', 'Hours'),
				self::CYCLE_DAY => Yii::t('label', 'Days'),
				self::CYCLE_WEEK => Yii::t('label', 'Weeks'),
				self::CYCLE_MONTH => Yii::t('label', 'Months'),
				self::CYCLE_YEAR => Yii::t('label', 'Years'),
			];
		} elseif ($mode === 'adjective') {
			return [
				self::CYCLE_MINUTE => Yii::t('label', 'Every {0}', Yii::t('label', 'Minute')),
				self::CYCLE_HOUR => Yii::t('label', 'Every {0}', Yii::t('label', 'Hour')),
				self::CYCLE_DAY => Yii::t('label', 'Every {0}', Yii::t('label', 'Day')),
				self::CYCLE_WEEK => Yii::t('label', 'Every {0}', Yii::t('label', 'Week')),
				self::CYCLE_MONTH => Yii::t('label', 'Every {0}', Yii::t('label', 'Month')),
				self::CYCLE_YEAR => Yii::t('label', 'Every {0}', Yii::t('label', 'Year')),
			];
		}
		return [
			self::CYCLE_MINUTE => Yii::t('label', 'Minute'),
			self::CYCLE_HOUR => Yii::t('label', 'Hour'),
			self::CYCLE_DAY => Yii::t('label', 'Day'),
			self::CYCLE_WEEK => Yii::t('label', 'Week'),
			self::CYCLE_MONTH => Yii::t('label', 'Month'),
			self::CYCLE_YEAR => Yii::t('label', 'Year'),
		];
	}

	/**
	 * Creates a cron expression.
	 *
	 * @param string $cycle The repeat cycle.
	 * @param array|string|int $at Custom parameters for the cycle.
	 * @return string|null The cron expression or null if is not valid.
	 */
	public static function createCronExpression($cycle = self::CYCLE_MINUTE, $at = '*')
	{
		$expression = [
			self::CYCLE_MINUTE => '*',
			self::CYCLE_HOUR => '*',
			self::CYCLE_DAY	=> '*',
			self::CYCLE_MONTH => '*',
			self::CYCLE_WEEK => '*',
			self::CYCLE_YEAR => '*',
		];

		if (!is_array($at)) {
			if ($at !== '*') {
				$at = [$cycle => $at];
			} else {
				$at = [];
			}
		}
		if (isset($at['time'])) {
			$time = explode(':', $at['time']);
			$at[self::CYCLE_HOUR] = $time[0];
			$at[self::CYCLE_MINUTE] = $time[1];
		}

		switch ($cycle) {
			case self::CYCLE_MINUTE:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ? "*/{$at[self::CYCLE_MINUTE]}" : '*';
				break;
			case self::CYCLE_HOUR:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ?: 0;
				$expression[self::CYCLE_HOUR] = $at[self::CYCLE_HOUR] ? "*/{$at[self::CYCLE_HOUR]}" : '*';
				break;
			case self::CYCLE_DAY:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ?: 0;
				$expression[self::CYCLE_HOUR] = $at[self::CYCLE_HOUR] ?: 0;
				$expression[self::CYCLE_DAY] = $at[self::CYCLE_DAY] ? "*/{$at[self::CYCLE_DAY]}" : '*';
				break;
			case self::CYCLE_MONTH:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ?: 0;
				$expression[self::CYCLE_HOUR] = $at[self::CYCLE_HOUR] ?: 0;
				$expression[self::CYCLE_DAY] = $at[self::CYCLE_DAY] ?: 1;
				$expression[self::CYCLE_MONTH] = $at[self::CYCLE_MONTH] ? "*/{$at[self::CYCLE_MONTH]}" : '*';
				break;
			case self::CYCLE_WEEK:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ?: 0;
				$expression[self::CYCLE_HOUR] = $at[self::CYCLE_HOUR] ?: 0;
				$expression[self::CYCLE_WEEK] = $at[self::CYCLE_DAY] ?: 1;
				break;
			case self::CYCLE_YEAR:
				$expression[self::CYCLE_MINUTE] = $at[self::CYCLE_MINUTE] ?: 0;
				$expression[self::CYCLE_HOUR] = $at[self::CYCLE_HOUR] ?: 0;
				$expression[self::CYCLE_DAY] = $at[self::CYCLE_DAY] ?: 1;
				$expression[self::CYCLE_MONTH] = $at[self::CYCLE_MONTH] ?: 1;
				$expression[self::CYCLE_YEAR] = $at[self::CYCLE_YEAR] ? "*/{$at[self::CYCLE_YEAR]}" : '*';
				break;
			default:
				break;
		}

		$expression = implode(' ', $expression);

		if (!CronExpression::isValidExpression($expression)) {
			return null;
		}

		return $expression;
	}
}
