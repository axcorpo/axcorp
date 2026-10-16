<?php

namespace backend\modules\marketing\models;

use common\models\MarketingCampaign;
use common\models\MarketingCampaignHasRecipient;
use common\models\MarketingRecipientSearchForm;
use common\models\ScheduledTask;
use Yii;

/**
 * Class FollowupMarketingCampaign
 */
class FollowupMarketingCampaign extends MarketingCampaign
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
			'resource' => FollowupMarketingCampaign::class,
			'resource_key' => $this->uuid,
		]);
		if (!$scheduledTask) {
			$scheduledTask = new ScheduledTask();
			$scheduledTask->type = ScheduledTask::TYPE_APP;
			$scheduledTask->status = ScheduledTask::STATUS_INACTIVE;
		}
		$scheduledTask->cron_expression = '* * * * * *';
		$scheduledTask->app_command = implode(' ', array_filter([
			'action' => 'marketing-campaign/run',
			'id' => $this->uuid,
			'model' => '"' . FollowupMarketingCampaign::class . '"',
		]));
		$scheduledTask->resource = FollowupMarketingCampaign::class;
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
	 * Gets the model formatted frequency with its translated unit of measure.
	 *
	 * @return mixed
	 */
	public function getFormattedFrequency()
	{
		if ($this->frequency == 0 || $this->frequency > 1) {
			$uoms = ScheduledTask::getCycleLabels('plural');
		} else {
			$uoms = ScheduledTask::getCycleLabels();
		}

		return implode(' ', [
			Yii::t('common', 'After'),
			$this->frequency,
			mb_strtolower($uoms[$this->cycle])
		]);
	}

	/**
	 * Starts the marketing campaign.
	 *
	 * @return bool The success or failure of the operation.
	 */
	public function startCampaign()
	{
		try {
			$this->start_at = (new \DateTime)->format('Y-m-d H:i:s');
			$this->end_at = null;
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

			$targetedRecipients = [];
			$messages = [];

			/** @var MarketingCampaignHasRecipient $marketingCampaignHasRecipient */
			foreach ($marketingCampaignHasRecipients->each(20) as $marketingCampaignHasRecipient) {
				// Check the campaign's run date for current marketing recipient
				$runDate = (new \DateTime($marketingCampaignHasRecipient->created_at))->modify("+{$this->frequency} {$this->cycle}");
				$currentDate = new \DateTime();
				if ($currentDate < $runDate) {
					continue;
				}

				$translation = $this->getTranslation();
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
	 * Adds recipients for follow-up marketing campaigns.
	 *
	 * @param array $params Parameters like recipients and their custom data.
	 * @param array $conditions Query conditions for finding marketing campaign models.
	 * @return bool
	 */
	public static function addFollowupRecipients($params, $conditions = [])
	{
		try {
			$recipients = (array) $params['recipients'];
			$data = (array) $params['data'];

			// Find all follow up marketing campaigns filtered by custom conditions
			$models = static::find()
				->andFilterWhere($conditions)
				->andWhere([
					'type' => static::TYPE_FOLLOW_UP,
					'status' => static::STATUS_ACTIVE,
					'deleted' => static::NO,
				]);

			/** @var static $model */
			foreach ($models->each(20) as $model) {
				// Find all eligible marketing campaign recipients
				$searchModel = new MarketingRecipientSearchForm();
				$searchModel->setAttributes((array) $model->getUnserializedValue('data')['filters']);
				$searchModel->getQuery()->andWhere(['mr.id' => $recipients]);
				if (!($eligibleMarketingRecipients = $searchModel->search())) {
					continue;
				}

				foreach ($eligibleMarketingRecipients as $marketingRecipient) {
					$marketingCampaignHasRecipient = new MarketingCampaignHasRecipient();
					$marketingCampaignHasRecipient->marketing_campaign_id = $model->id;
					$marketingCampaignHasRecipient->marketing_recipient_id = $marketingRecipient->id;
					$marketingCampaignHasRecipient->data = !empty($data) ? @serialize($data) : null;
					if (!$marketingCampaignHasRecipient->save()) {
						throw new \Exception();
					}
				}
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
			static::STATUS_INACTIVE => [
				'label' => Yii::t('label', 'Inactive'),
				'color' => 'danger',
			],
			static::STATUS_ACTIVE => [
				'label' => Yii::t('label', 'Active'),
				'color' => 'success',
			],
		];
	}
}
