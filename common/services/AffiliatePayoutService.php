<?php

namespace common\services;

use common\helpers\PrivateFileHelper;
use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\AffiliatePayout;
use common\models\AffiliateSettings;
use Yii;
use yii\base\Event;
use yii\base\UserException;
use yii\web\UploadedFile;

/**
 * Payouts to affiliates: grouping PAYABLE commissions, the affiliate's invoice, the
 * payment proofs and the final settlement. No UI here — the backend (admin) and the
 * frontend account (affiliate) call these methods after their own access checks.
 *
 * Every method either completes or throws a [[UserException]] whose message is safe to
 * show (flash it); the payout and commissions are then unchanged. State changes that
 * touch several rows run in one transaction.
 *
 * Rules: one currency per payout (totals are never converted); one affiliate invoice per
 * payout; a commission belongs to at most one payout (`affiliate_payout_id`) and stays
 * PAYABLE until the payout is PAID; cancelling a payout returns its commissions to the
 * pool. The total may include storno offsets (negative commissions) and must be positive
 * and at least the `minimumPayout` setting.
 *
 * Hooks for notifications (e-mail task): [[EVENT_AFTER_PAID]] and
 * [[EVENT_INVOICE_REJECTED]] are triggered on the payout model.
 */
class AffiliatePayoutService
{
	/**
	 * Triggered on the AffiliatePayout after markPaid() committed.
	 */
	const EVENT_AFTER_PAID = 'affiliatePayout.afterPaid';
	/**
	 * Triggered on the AffiliatePayout after rejectInvoice() saved.
	 */
	const EVENT_INVOICE_REJECTED = 'affiliatePayout.invoiceRejected';

	/**
	 * The affiliate's commissions available for a new payout, per currency:
	 * `['RON' => ['amount' => 120.5, 'count' => 4, 'canCreate' => true], ...]`.
	 * `canCreate` applies the same checks as [[createPayout()]] (positive, minimumPayout).
	 *
	 * @param Affiliate $affiliate
	 * @return array
	 */
	public function getPayableSummary(Affiliate $affiliate)
	{
		$rows = AffiliateCommission::find()
			->select(['currency', 'amount' => 'SUM([[amount]])', 'count' => 'COUNT(*)'])
			->where([
				'affiliate_id' => $affiliate->getAttribute('id'),
				'status' => AffiliateCommission::STATUS_PAYABLE,
				'affiliate_payout_id' => null,
			])
			->groupBy('currency')
			->asArray()
			->all();

		$minimum = AffiliateSettings::getMinimumPayout();
		$summary = [];
		foreach ($rows as $row) {
			$amount = round((float) $row['amount'], 2);
			$summary[$row['currency']] = [
				'amount' => $amount,
				'count' => (int) $row['count'],
				'canCreate' => $amount > 0 && $amount >= $minimum,
			];
		}
		return $summary;
	}

	/**
	 * Groups all of the affiliate's unassigned PAYABLE commissions in `$currency` into a
	 * new OPEN payout (awaiting the affiliate's invoice).
	 *
	 * @param Affiliate $affiliate
	 * @param string $currency
	 * @param string|null $notes
	 * @return AffiliatePayout
	 * @throws UserException nothing payable, total not positive or below minimumPayout
	 */
	public function createPayout(Affiliate $affiliate, $currency, $notes = null)
	{
		$db = AffiliatePayout::getDb();
		$transaction = $db->beginTransaction();
		try {
			// Locked, so two admins clicking at once cannot put a commission in two payouts.
			$rows = $db->createCommand(
				'SELECT [[id]], [[amount]] FROM ' . AffiliateCommission::tableName()
				. ' WHERE [[affiliate_id]] = :affiliate AND [[currency]] = :currency AND [[status]] = :status'
				. ' AND [[affiliate_payout_id]] IS NULL FOR UPDATE',
				[
					':affiliate' => $affiliate->getAttribute('id'),
					':currency' => $currency,
					':status' => AffiliateCommission::STATUS_PAYABLE,
				]
			)->queryAll();

			if (!$rows) {
				throw new UserException(Yii::t('error', 'There are no payable commissions in {currency}.', ['currency' => $currency]));
			}
			$total = round(array_sum(array_map('floatval', array_column($rows, 'amount'))), 2);
			if ($total <= 0) {
				throw new UserException(Yii::t('error', 'The payable total in {currency} is not positive.', ['currency' => $currency]));
			}
			$minimum = AffiliateSettings::getMinimumPayout();
			if ($total < $minimum) {
				throw new UserException(Yii::t('error', 'The payable total ({total} {currency}) is below the minimum payout ({minimum}).', [
					'total' => $total,
					'currency' => $currency,
					'minimum' => $minimum,
				]));
			}

			$payout = new AffiliatePayout();
			$payout->affiliate_id = $affiliate->getAttribute('id');
			$payout->amount = $total;
			$payout->currency = $currency;
			$payout->status = AffiliatePayout::STATUS_OPEN;
			$payout->notes = $notes;
			if (!$payout->save()) {
				throw new UserException(Yii::t('error', 'The payout could not be saved.'));
			}

			AffiliateCommission::updateAll(
				['affiliate_payout_id' => $payout->getAttribute('id'), 'updated_at' => date('Y-m-d H:i:s')],
				['id' => array_column($rows, 'id')]
			);

			$transaction->commit();
			return $payout;
		} catch (\Exception $e) {
			$transaction->rollBack();
			throw $e;
		}
	}

	/**
	 * Stores the affiliate's invoice for the payout (replacing a previous, not accepted
	 * one) and puts it up for review: invoice PENDING, payout INVOICE_RECEIVED.
	 *
	 * @param AffiliatePayout $payout
	 * @param UploadedFile $file PDF/JPG/PNG, see PrivateFileHelper
	 * @param string $invoiceNumber
	 * @param string $invoiceDate Y-m-d
	 * @param bool $deleteTempFile false copies the temp file (non-HTTP uploads, tests)
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function attachInvoice(AffiliatePayout $payout, UploadedFile $file, $invoiceNumber, $invoiceDate, $deleteTempFile = true)
	{
		$this->assertOpen($payout);
		if ((int) $payout->invoice_status === AffiliatePayout::INVOICE_STATUS_ACCEPTED) {
			throw new UserException(Yii::t('error', 'The invoice of this payout was already accepted.'));
		}
		$invoiceNumber = trim((string) $invoiceNumber);
		if ($invoiceNumber === '') {
			throw new UserException(Yii::t('error', 'The invoice number is required.'));
		}
		// A form date picker sends Y-m-d; the column is DATETIME like every date in the app.
		$date = \DateTime::createFromFormat('!Y-m-d H:i:s', (string) $invoiceDate)
			?: \DateTime::createFromFormat('!Y-m-d', (string) $invoiceDate);
		if (!$date || !in_array($invoiceDate, [$date->format('Y-m-d'), $date->format('Y-m-d H:i:s')], true)) {
			throw new UserException(Yii::t('error', 'The invoice date is not valid.'));
		}

		$name = $this->storeFile($payout, $file, $deleteTempFile);
		$previous = $payout->invoice_file;

		$payout->invoice_number = $invoiceNumber;
		$payout->invoice_date = $date->format('Y-m-d H:i:s');
		$payout->invoice_file = $name;
		$payout->invoice_status = AffiliatePayout::INVOICE_STATUS_PENDING;
		$payout->invoice_rejection_note = null;
		$payout->status = AffiliatePayout::STATUS_INVOICE_RECEIVED;
		if (!$payout->save()) {
			PrivateFileHelper::delete($payout->getFileSubdir(), $name);
			throw new UserException($this->firstError($payout, Yii::t('error', 'The invoice could not be saved.')));
		}
		if ($previous && $previous !== $name) {
			PrivateFileHelper::delete($payout->getFileSubdir(), $previous);
		}
		return $payout;
	}

	/**
	 * Accepts the affiliate's invoice under review.
	 *
	 * @param AffiliatePayout $payout
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function acceptInvoice(AffiliatePayout $payout)
	{
		$this->assertOpen($payout);
		if ((int) $payout->invoice_status !== AffiliatePayout::INVOICE_STATUS_PENDING) {
			throw new UserException(Yii::t('error', 'There is no invoice awaiting review.'));
		}
		$payout->invoice_status = AffiliatePayout::INVOICE_STATUS_ACCEPTED;
		$payout->invoice_rejection_note = null;
		$this->save($payout);
		return $payout;
	}

	/**
	 * Rejects the affiliate's invoice (under review, or accepted but not yet paid) with a
	 * reason; the payout goes back to OPEN, awaiting a new invoice. The rejected file is
	 * kept until the affiliate uploads the replacement.
	 *
	 * @param AffiliatePayout $payout
	 * @param string $reason shown to the affiliate
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function rejectInvoice(AffiliatePayout $payout, $reason)
	{
		$this->assertOpen($payout);
		if (!in_array((int) $payout->invoice_status, [AffiliatePayout::INVOICE_STATUS_PENDING, AffiliatePayout::INVOICE_STATUS_ACCEPTED], true)) {
			throw new UserException(Yii::t('error', 'There is no invoice to reject.'));
		}
		$reason = trim((string) $reason);
		if ($reason === '') {
			throw new UserException(Yii::t('error', 'Please give the reason of the rejection.'));
		}
		$payout->invoice_status = AffiliatePayout::INVOICE_STATUS_REJECTED;
		$payout->invoice_rejection_note = $reason;
		$payout->status = AffiliatePayout::STATUS_OPEN;
		$this->save($payout);
		$payout->trigger(static::EVENT_INVOICE_REJECTED, new Event());
		return $payout;
	}

	/**
	 * Adds payment proofs (bank statements) to a payout that is open or already paid.
	 * All files are validated before any is stored.
	 *
	 * @param AffiliatePayout $payout
	 * @param UploadedFile[] $files
	 * @param bool $deleteTempFile
	 * @return string[] the stored names added
	 * @throws UserException
	 */
	public function addProofFiles(AffiliatePayout $payout, array $files, $deleteTempFile = true)
	{
		if ((int) $payout->status === AffiliatePayout::STATUS_CANCELLED || (int) $payout->deleted === AffiliatePayout::YES) {
			throw new UserException(Yii::t('error', 'This payout is cancelled.'));
		}
		$files = array_values(array_filter($files));
		if (!$files) {
			throw new UserException(Yii::t('error', 'Please upload a file.'));
		}
		foreach ($files as $file) {
			if ($errors = PrivateFileHelper::validate($file)) {
				throw new UserException(reset($errors));
			}
		}

		$added = [];
		try {
			foreach ($files as $file) {
				$added[] = $this->storeFile($payout, $file, $deleteTempFile);
			}
			$payout->setProofFileList(array_merge($payout->getProofFileList(), $added));
			$this->save($payout);
		} catch (\Exception $e) {
			foreach ($added as $name) {
				PrivateFileHelper::delete($payout->getFileSubdir(), $name);
			}
			throw $e;
		}
		return $added;
	}

	/**
	 * Removes one payment proof (record and file).
	 *
	 * @param AffiliatePayout $payout
	 * @param string $name stored name
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function removeProofFile(AffiliatePayout $payout, $name)
	{
		$list = $payout->getProofFileList();
		if (!in_array($name, $list, true)) {
			throw new UserException(Yii::t('error', 'The file does not exist.'));
		}
		$payout->setProofFileList(array_diff($list, [$name]));
		$this->save($payout);
		PrivateFileHelper::delete($payout->getFileSubdir(), $name);
		return $payout;
	}

	/**
	 * Settles the payout: payout PAID with `paid_at`, its commissions PAID.
	 *
	 * @param AffiliatePayout $payout
	 * @param string|null $paidAt "Y-m-d H:i:s" (or "Y-m-d"); null = now
	 * @param bool $requireAcceptedInvoice false lets the admin pay before the affiliate's
	 * invoice is accepted (e.g. a non-VAT individual with a different document)
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function markPaid(AffiliatePayout $payout, $paidAt = null, $requireAcceptedInvoice = true)
	{
		$this->assertOpen($payout);
		if ($requireAcceptedInvoice && (int) $payout->invoice_status !== AffiliatePayout::INVOICE_STATUS_ACCEPTED) {
			throw new UserException(Yii::t('error', 'The affiliate invoice must be accepted before the payout is marked as paid.'));
		}
		$paidAt = $paidAt ? date('Y-m-d H:i:s', strtotime($paidAt)) : date('Y-m-d H:i:s');

		$transaction = AffiliatePayout::getDb()->beginTransaction();
		try {
			$payout->status = AffiliatePayout::STATUS_PAID;
			$payout->paid_at = $paidAt;
			$this->save($payout);
			AffiliateCommission::updateAll(
				['status' => AffiliateCommission::STATUS_PAID, 'updated_at' => date('Y-m-d H:i:s')],
				['affiliate_payout_id' => $payout->getAttribute('id')]
			);
			$transaction->commit();
		} catch (\Exception $e) {
			$transaction->rollBack();
			throw $e;
		}
		$payout->trigger(static::EVENT_AFTER_PAID, new Event());
		return $payout;
	}

	/**
	 * Cancels an unpaid payout; its commissions return to the PAYABLE pool. The payout row
	 * and its files are kept for the record.
	 *
	 * @param AffiliatePayout $payout
	 * @param string|null $reason appended to the notes
	 * @return AffiliatePayout
	 * @throws UserException
	 */
	public function cancel(AffiliatePayout $payout, $reason = null)
	{
		$this->assertOpen($payout);

		$transaction = AffiliatePayout::getDb()->beginTransaction();
		try {
			AffiliateCommission::updateAll(
				['affiliate_payout_id' => null, 'updated_at' => date('Y-m-d H:i:s')],
				['affiliate_payout_id' => $payout->getAttribute('id'), 'status' => [AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE]]
			);
			$payout->status = AffiliatePayout::STATUS_CANCELLED;
			if ($reason = trim((string) $reason)) {
				$payout->notes = trim($payout->notes . "\n" . $reason);
			}
			$this->save($payout);
			$transaction->commit();
		} catch (\Exception $e) {
			$transaction->rollBack();
			throw $e;
		}
		return $payout;
	}

	/**
	 * @param AffiliatePayout $payout
	 * @throws UserException when the payout is paid, cancelled or deleted
	 */
	protected function assertOpen(AffiliatePayout $payout)
	{
		if (!$payout->getIsOpen()) {
			throw new UserException(Yii::t('error', 'This payout can no longer be changed.'));
		}
	}

	/**
	 * @param AffiliatePayout $payout
	 * @param UploadedFile $file
	 * @param bool $deleteTempFile
	 * @return string the stored name
	 * @throws UserException
	 */
	protected function storeFile(AffiliatePayout $payout, UploadedFile $file, $deleteTempFile)
	{
		try {
			return PrivateFileHelper::save($file, $payout->getFileSubdir(), $deleteTempFile);
		} catch (\yii\base\InvalidArgumentException $e) {
			throw new UserException($e->getMessage());
		} catch (\yii\base\Exception $e) {
			Yii::error(['message' => 'Affiliate payout file could not be stored.', 'payout' => $payout->uuid, 'error' => $e->getMessage()], __METHOD__);
			throw new UserException(Yii::t('error', 'The file could not be saved.'));
		}
	}

	/**
	 * @param AffiliatePayout $payout
	 * @throws UserException
	 */
	protected function save(AffiliatePayout $payout)
	{
		if (!$payout->save()) {
			throw new UserException($this->firstError($payout, Yii::t('error', 'The payout could not be saved.')));
		}
	}

	/**
	 * @param \yii\base\Model $model
	 * @param string $fallback
	 * @return string
	 */
	protected function firstError($model, $fallback)
	{
		$errors = $model->getFirstErrors();
		return $errors ? reset($errors) : $fallback;
	}
}
