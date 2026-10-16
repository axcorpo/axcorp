<?php

namespace common\services;

use common\models\Affiliate;
use DateTime;
use Yii;
use yii\base\UserException;

/**
 * The review of an affiliate application (docs/afiliere.md, "Cererea de afiliere").
 *
 * An application is an `affiliate` row in STATUS_PENDING, created by the public
 * "become an affiliate" page together with the applicant's user account and company. The
 * admin either approves it — the row turns ACTIVE and starts attributing signups — or
 * rejects it with a reason (STATUS_REJECTED). Each decision is e-mailed to the applicant
 * (templates 111/112); a failed e-mail never undoes the decision.
 */
class AffiliateApplicationService
{
	/**
	 * @var AffiliateNotificationService|null
	 */
	private $_notifier;

	/**
	 * Approves a pending application: the affiliate becomes active.
	 *
	 * @param Affiliate $affiliate
	 * @throws UserException when it is not a pending application or cannot be saved
	 */
	public function approve(Affiliate $affiliate)
	{
		$this->assertPending($affiliate);

		$affiliate->status = Affiliate::STATUS_ACTIVE;
		$affiliate->rejection_reason = null;
		$affiliate->reviewed_at = $this->getNow();
		$this->save($affiliate);

		$this->getNotifier()->sendApplicationApproved($affiliate);
	}

	/**
	 * Rejects a pending application, with the reason the applicant is told.
	 *
	 * @param Affiliate $affiliate
	 * @param string $reason
	 * @throws UserException when it is not a pending application or cannot be saved
	 */
	public function reject(Affiliate $affiliate, $reason)
	{
		$this->assertPending($affiliate);
		$reason = trim((string) $reason);
		if ($reason === '') {
			throw new UserException(Yii::t('error', 'The rejection reason is required.'));
		}

		$affiliate->status = Affiliate::STATUS_REJECTED;
		$affiliate->rejection_reason = $reason;
		$affiliate->reviewed_at = $this->getNow();
		$this->save($affiliate);

		$this->getNotifier()->sendApplicationRejected($affiliate);
	}

	/**
	 * @param Affiliate $affiliate
	 * @throws UserException
	 */
	protected function assertPending(Affiliate $affiliate)
	{
		if (!$affiliate->getIsPending()) {
			throw new UserException(Yii::t('error', 'This affiliate application has already been reviewed.'));
		}
	}

	/**
	 * @param Affiliate $affiliate
	 * @throws UserException
	 */
	protected function save(Affiliate $affiliate)
	{
		if (!$affiliate->save(true, ['status', 'rejection_reason', 'reviewed_at', 'updated_at', 'updated_by'])) {
			$errors = $affiliate->getFirstErrors();
			throw new UserException($errors ? reset($errors) : Yii::t('common', 'Cannot save the record.'));
		}
	}

	/**
	 * @return AffiliateNotificationService
	 */
	public function getNotifier()
	{
		if ($this->_notifier === null) {
			$this->_notifier = new AffiliateNotificationService();
		}
		return $this->_notifier;
	}

	/**
	 * @param AffiliateNotificationService $notifier
	 */
	public function setNotifier(AffiliateNotificationService $notifier)
	{
		$this->_notifier = $notifier;
	}

	/**
	 * @return string
	 */
	protected function getNow()
	{
		return (new DateTime())->format('Y-m-d H:i:s');
	}
}
