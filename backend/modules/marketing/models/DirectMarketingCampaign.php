<?php

namespace backend\modules\marketing\models;

use common\models\MarketingCampaign;
use common\models\MarketingCampaignHasRecipient;
use common\models\ScheduledTask;
use Yii;
use yii\helpers\Html;

/**
 * Class DirectMarketingCampaign
 */
class DirectMarketingCampaign extends MarketingCampaign
{
	/**
	 * @var ScheduledTask The scheduled task instance.
	 */
	private $_scheduledTask;


	/**
	 * Saves the scheduled task model instance.
	 * This method ensures a new record if does not exist.
	 *
	 * @return ScheduledTask|null
	 */
	public function saveScheduledTask()
	{
		// Find existing record without taking into account the deleted attribute
		$scheduledTask = ScheduledTask::findOne([
			'resource' => DirectMarketingCampaign::class,
			'resource_key' => $this->uuid,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
			$scheduledTask->type = ScheduledTask::TYPE_APP;
			$scheduledTask->status = ScheduledTask::STATUS_INACTIVE;
		}
		$scheduledTask->cron_expression = ScheduledTask::createCronExpression($this->cycle);
		$scheduledTask->app_command = implode(' ', array_filter([
			'action' => 'marketing-campaign/run',
			'id' => $this->uuid,
			'model' => '"' . DirectMarketingCampaign::class . '"',
		]));
		$scheduledTask->resource = DirectMarketingCampaign::class;
		$scheduledTask->resource_key = $this->uuid;
		$scheduledTask->application = str_replace('app-', '', Yii::$app->id);
		if (!$scheduledTask->save()) {
			$this->addErrors($scheduledTask->getErrors());
			return null;
		}
		return $scheduledTask;
	}

	/**
	 * Gets the scheduled task model instance.
	 *
	 * @return ScheduledTask|null
	 */
	public function getScheduledTask()
	{
		if (!$this->_scheduledTask) {
			$this->_scheduledTask = $this->saveScheduledTask();
		}
		return $this->_scheduledTask;
	}

	/**
	 * Gets the model formatted progress.
	 *
	 * @param bool $asHtml
	 * @return mixed
	 */
	public function getFormattedProgress($asHtml = true)
	{
		$total = count($this->marketingCampaignHasRecipients) ?: 1;
		$current = count(array_filter($this->marketingCampaignHasRecipients, function ($mchr) {
			return $mchr->sent_at !== null;
		}));
		$progressValue = min(round(($current * 100) / $total), 100);

		if ($asHtml === true) {
			$progressBar = Html::tag('div', "{$progressValue}%", [
				'class' => 'progress-bar progress-bar-info progress-bar-striped',
				'style' => "width: {$progressValue}%",
			]);
			return Html::tag('div', $progressBar, ['class' => 'progress']);
		}

		return "{$progressValue}%";
	}

	/**
	 * Gets the model formatted frequency with its translated unit of measure.
	 *
	 * @param string $separator
	 * @return mixed
	 */
	public function getFormattedFrequency($separator = '/')
	{
		return $this->frequency . $separator . mb_strtolower(ScheduledTask::getCycleLabels()[$this->cycle]);
	}

	/**
	 * Starts the marketing campaign.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function startCampaign()
	{
		try {
			if ($this->status != self::STATUS_PAUSED) {
				$this->start_at = (new \DateTime)->format('Y-m-d H:i:s');
				$this->end_at = null;
			}
			$this->status = self::STATUS_ACTIVE;
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->getScheduledTask()->enable()) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Restarts the marketing campaign.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function restartCampaign()
	{
		if ($result = $this->startCampaign()) {
			MarketingCampaignHasRecipient::updateAll([
				'sent_at' => null,
			], [
				'marketing_campaign_id' => $this->id,
			]);
		}

		return $result;
	}

	/**
	 * Pauses the marketing campaign.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function pauseCampaign()
	{
		try {
			$this->status = self::STATUS_PAUSED;
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->getScheduledTask()->disable()) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Stops the marketing campaign.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function stopCampaign()
	{
		try {
			if ($this->start_at) {
				$this->end_at = (new \DateTime)->format('Y-m-d H:i:s');
			}
			$this->status = self::STATUS_INACTIVE;
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->getScheduledTask()->disable()) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Marks the marketing campaign as complete.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function completeCampaign()
	{
		try {
			$this->status = self::STATUS_COMPLETE;
			$this->end_at = (new \DateTime)->format('Y-m-d H:i:s');
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->getScheduledTask()->disable()) {
				throw new \Exception();
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Runs this marketing campaign.
	 *
	 * @return bool
	 */
	public function run()
	{
		try {
			$marketingCampaignHasRecipients = MarketingCampaignHasRecipient::find()
				->alias('mchr')
				->joinWith([
					'marketingRecipient mr',
					'marketingRecipient.user u',
				])
				->andWhere(['mchr.marketing_campaign_id' => $this->id])
				->andWhere(['IS', 'mchr.sent_at', null]);

			// Mark the campaign as done if there are no more messages to be sent
			if (!($marketingRecipientsCount = $marketingCampaignHasRecipients->count())) {
				$this->completeCampaign();
				return true;
			}

			$translation = $this->getTranslation();
			$targetedRecipients = [];
			$messages = [];

			/** @var MarketingCampaignHasRecipient $marketingCampaignHasRecipient */
			foreach ($marketingCampaignHasRecipients->limit($this->frequency)->each(20) as $marketingCampaignHasRecipient) {
				$marketingRecipient = $marketingCampaignHasRecipient->marketingRecipient;
				$targetedRecipients[] = $marketingRecipient->id;
				$shortCodeValues = $marketingRecipient->getShortCodeValues();

				// Compose the message and push it to the stack
				if ($this->variant == static::VARIANT_EMAIL) {
					if (!filter_var($marketingRecipient->email, FILTER_VALIDATE_EMAIL)) {
						continue;
					}
					$messages[] = Yii::$app->mailer->compose()
						->setTo([$marketingRecipient->email => $marketingRecipient->user->fullName])
						->setSubject(strtr($translation->subject, $shortCodeValues))
						->setHtmlBody(strtr($translation->content, $shortCodeValues));
				} elseif ($this->variant == static::VARIANT_SMS) {
					if (empty($marketingRecipient->phone)) {
						continue;
					}
					$messages[] = Yii::$app->sms->compose()
						->setTo($marketingRecipient->phone)
						->setTextBody(strtr(strip_tags($translation->content), $shortCodeValues));
				}

				// Mark the campaign as done if there are no more messages to be sent
				if ($marketingRecipientsCount == count($messages)) {
					$this->completeCampaign();
					break;
				}
			}

			// Send the messages
			if (!empty($messages)) {
				if ($this->variant == static::VARIANT_EMAIL) {
					Yii::$app->mailer->sendMultiple($messages);
				} elseif ($this->variant == static::VARIANT_SMS) {
					Yii::$app->sms->sendMultiple($messages);
				}

				// Mark all recipients as received the message for this campaign
				MarketingCampaignHasRecipient::updateAll([
					'sent_at' => (new \DateTime)->format('Y-m-d H:i:s'),
				], [
					'marketing_campaign_id' => $this->id,
					'marketing_recipient_id' => $targetedRecipients,
				]);
			}

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @inheritdoc
	 */
	public static function getStatusLabels()
	{
		return [
			self::STATUS_INACTIVE => [
				'label' => Yii::t('label', 'Inactive'),
				'color' => 'danger',
			],
			self::STATUS_ACTIVE => [
				'label' => Yii::t('label', 'Active'),
				'color' => 'success',
			],
			self::STATUS_PAUSED => [
				'label' => Yii::t('label', 'Paused'),
				'color' => 'warning',
			],
			self::STATUS_COMPLETE => [
				'label' => Yii::t('label', 'Complete'),
				'color' => 'info',
			],
		];
	}
}
