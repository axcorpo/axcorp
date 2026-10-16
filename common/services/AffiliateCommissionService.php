<?php

namespace common\services;

use common\models\Affiliate;
use common\models\AffiliateCommission;
use common\models\AffiliateSettings;
use common\models\Invoice;
use common\models\Subscriber;
use DateTime;
use Yii;
use yii\db\IntegrityException;
use yii\db\Query;

/**
 * The commission engine: turns the master invoices (invoice) of affiliate-referred
 * subscribers (subscriber.affiliate_id, set at signup) into affiliate_commission rows.
 * Affiliation lives on master only: the stores (workspaces) take no part in it.
 * Run by `php yii affiliate/compute-commissions` (hourly); idempotent and safe to rerun or
 * run concurrently — one commission per invoice is guaranteed by the unique key on
 * invoice_id, and every step only moves rows forward.
 *
 * Rules (docs/afiliere.md, decisions of 1 Oct 2026):
 * - Source: invoice of type INVOICE with status PAID (paid_at set), not deleted, whose
 *   subscriber is attributed to an ACTIVE, non-deleted affiliate. Proformas never count
 *   (the fiscal invoice is the one marked paid).
 * - Base: the NET amount. invoice.amount is VAT-inclusive and `vat` is a rate, so
 *   net = round(amount / (1 + vat / 100), 2) — the same formula as Invoice::getSubtotal().
 *   Currency is the invoice's; totals are kept per currency, never converted.
 * - Percentage: the affiliate's at computation time, copied onto the commission.
 * - Duration: when the affiliate has `commission_months`, only invoices paid before
 *   subscriber.created_at + N months earn (unknown signup date = no limit).
 * - Storno: a RECTIFY invoice yields a NEGATIVE commission. When it points (`parent_id`)
 *   at an invoice that earned a commission, it reuses that commission's percentage and
 *   ignores the duration window (the original was inside it); when the parent earned
 *   nothing (or has not been processed yet) it is skipped and reconsidered next run.
 *   A rectify without a parent follows the normal rules. The amount sign is taken from
 *   the type, not the stored value (rectify amounts are stored positive; OblioInvoice
 *   negates them on export).
 * - Void: a commission not yet paid out (PENDING/PAYABLE, not in a payout) is CANCELLED
 *   when its invoice is deleted (deleted = 1) or, for an INVOICE, is no longer PAID; a
 *   rectify commission is cancelled with the commission it offsets. Commissions already
 *   inside an open payout are left alone (logged): cancelling the payout returns them to
 *   PAYABLE and the next run cancels them. A cancelled commission is never revived.
 * - Hold: a new commission is PENDING until payable_at = earned_at + holdDays (setting,
 *   default 30), then PAYABLE.
 */
class AffiliateCommissionService
{
	/**
	 * @var string|null "Y-m-d H:i:s" to compute against; null = the current time. Lets
	 * tests (and back-dated reruns) pin the clock.
	 */
	public $now;

	/**
	 * @var int|null Hold period in days; null = the `holdDays` setting.
	 */
	public $holdDays;

	/**
	 * @var int Rows fetched per batch when scanning invoices.
	 */
	public $batchSize = 500;

	/**
	 * @param array $config public property values
	 */
	public function __construct(array $config = [])
	{
		foreach ($config as $name => $value) {
			$this->$name = $value;
		}
	}

	/**
	 * One full pass: create the missing commissions, cancel the voided ones, release the
	 * ones whose hold period is over.
	 *
	 * @return array{created: int, cancelled: int, payable: int}
	 */
	public function run()
	{
		$created = $this->createCommissions();
		$cancelled = $this->cancelVoidedCommissions();
		$payable = $this->releaseHeldCommissions();

		return ['created' => $created, 'cancelled' => $cancelled, 'payable' => $payable];
	}

	/**
	 * Creates the commission of every qualifying invoice that has none yet.
	 *
	 * @return int the number of commissions created
	 */
	public function createCommissions()
	{
		$query = (new Query())
			->select([
				'i.id', 'i.parent_id', 'i.type', 'i.amount', 'i.vat', 'i.currency',
				'i.paid_at', 'i.issued_at', 'i.created_at',
				'subscriber_id' => 's.id',
				'subscriber_created_at' => 's.created_at',
				'affiliate_id' => 'a.id',
				'a.commission_percentage',
				'a.commission_months',
			])
			->from(['i' => Invoice::tableName()])
			->innerJoin(['s' => Subscriber::tableName()], '[[s.id]] = [[i.subscriber_id]]')
			->innerJoin(['a' => Affiliate::tableName()], '[[a.id]] = [[s.affiliate_id]]')
			->leftJoin(['c' => AffiliateCommission::tableName()], '[[c.invoice_id]] = [[i.id]]')
			->where([
				'c.id' => null,
				'i.deleted' => Invoice::NO,
				'a.status' => Affiliate::STATUS_ACTIVE,
				'a.deleted' => Affiliate::NO,
			])
			->andWhere(['or',
				['and', ['i.type' => Invoice::TYPE_INVOICE, 'i.status' => Invoice::STATUS_PAID], ['not', ['i.paid_at' => null]]],
				['i.type' => Invoice::TYPE_RECTIFY],
			])
			// Invoices before rectifies, so a storno finds its original's commission when
			// both arrive in the same run.
			->orderBy(['i.type' => SORT_ASC, 'i.paid_at' => SORT_ASC]);

		$created = 0;
		foreach ($query->each($this->batchSize) as $row) {
			if ($this->createCommission($row)) {
				$created++;
			}
		}
		return $created;
	}

	/**
	 * Cancels the not-yet-paid-out commissions whose invoice was voided.
	 *
	 * @return int the number of commissions cancelled
	 */
	public function cancelVoidedCommissions()
	{
		$voided = ['or',
			['i.deleted' => Invoice::YES],
			['and', ['i.type' => Invoice::TYPE_INVOICE], ['!=', 'i.status', Invoice::STATUS_PAID]],
		];
		$cancelled = 0;

		// Commissions already grouped in an open payout: not touched here (the payout would
		// silently change under the affiliate's invoice) — reported for the admin.
		$inPayout = (new Query())
			->from(['c' => AffiliateCommission::tableName()])
			->innerJoin(['i' => Invoice::tableName()], '[[i.id]] = [[c.invoice_id]]')
			->where(['c.status' => [AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE]])
			->andWhere(['not', ['c.affiliate_payout_id' => null]])
			->andWhere($voided)
			->count();
		if ($inPayout) {
			Yii::warning(['message' => 'Voided invoices have commissions inside an open affiliate payout; cancel the payout to release them.', 'count' => (int) $inPayout], __METHOD__);
		}

		$ids = (new Query())
			->select('c.id')
			->from(['c' => AffiliateCommission::tableName()])
			->innerJoin(['i' => Invoice::tableName()], '[[i.id]] = [[c.invoice_id]]')
			->where(['c.status' => [AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE], 'c.affiliate_payout_id' => null])
			->andWhere($voided)
			->column();
		$cancelled += $this->setStatus($ids, AffiliateCommission::STATUS_CANCELLED);

		// A storno offset whose original commission is cancelled offsets nothing any more.
		$ids = (new Query())
			->select('c.id')
			->from(['c' => AffiliateCommission::tableName()])
			->innerJoin(['i' => Invoice::tableName()], '[[i.id]] = [[c.invoice_id]]')
			->innerJoin(['pc' => AffiliateCommission::tableName()], '[[pc.invoice_id]] = [[i.parent_id]]')
			->where([
				'c.type' => Invoice::TYPE_RECTIFY,
				'c.status' => [AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE],
				'c.affiliate_payout_id' => null,
				'pc.status' => AffiliateCommission::STATUS_CANCELLED,
			])
			->column();
		$cancelled += $this->setStatus($ids, AffiliateCommission::STATUS_CANCELLED);

		return $cancelled;
	}

	/**
	 * PENDING → PAYABLE for the commissions whose hold period is over.
	 *
	 * @return int the number of commissions released
	 */
	public function releaseHeldCommissions()
	{
		return AffiliateCommission::updateAll(
			['status' => AffiliateCommission::STATUS_PAYABLE, 'updated_at' => $this->getNow()],
			['and', ['status' => AffiliateCommission::STATUS_PENDING], ['<=', 'payable_at', $this->getNow()]]
		);
	}

	/**
	 * Admin bulk action: releases PENDING commissions before their hold period ends.
	 *
	 * @param string[] $ids commission ids (raw bytes or canonical uuids)
	 * @return int the number of commissions made payable
	 */
	public function approve(array $ids)
	{
		$ids = $this->findIds($ids, ['status' => AffiliateCommission::STATUS_PENDING]);
		return $this->setStatus($ids, AffiliateCommission::STATUS_PAYABLE);
	}

	/**
	 * Admin bulk action: cancels PENDING/PAYABLE commissions not yet in a payout (paid and
	 * payout-bound ones are skipped).
	 *
	 * @param string[] $ids commission ids (raw bytes or canonical uuids)
	 * @return int the number of commissions cancelled
	 */
	public function cancel(array $ids)
	{
		$ids = $this->findIds($ids, [
			'status' => [AffiliateCommission::STATUS_PENDING, AffiliateCommission::STATUS_PAYABLE],
			'affiliate_payout_id' => null,
		]);
		return $this->setStatus($ids, AffiliateCommission::STATUS_CANCELLED);
	}

	/**
	 * The commission base of a VAT-inclusive amount: the net, rounded to 2 decimals.
	 *
	 * @param string|float $amount gross amount
	 * @param string|float|null $vat VAT rate in percent (e.g. 19)
	 * @return float
	 */
	public static function netAmount($amount, $vat)
	{
		$vat = (float) $vat;
		return round((float) $amount / (1 + $vat / 100), 2);
	}

	/**
	 * The commission of a base at a percentage, rounded to 2 decimals (sign kept).
	 *
	 * @param float $base
	 * @param string|float $percentage
	 * @return float
	 */
	public static function commissionAmount($base, $percentage)
	{
		return round((float) $base * (float) $percentage / 100, 2);
	}

	/**
	 * Creates the commission of one candidate row of [[createCommissions()]].
	 *
	 * @param array $row
	 * @return bool whether a commission was created
	 */
	protected function createCommission(array $row)
	{
		$isRectify = (int) $row['type'] === Invoice::TYPE_RECTIFY;
		$percentage = $row['commission_percentage'];
		$affiliateId = $row['affiliate_id'];
		$earnedAt = $isRectify
			? ($row['paid_at'] ?: ($row['issued_at'] ?: ($row['created_at'] ?: $this->getNow())))
			: $row['paid_at'];

		if ($isRectify && $row['parent_id'] !== null) {
			$original = AffiliateCommission::find()->where(['invoice_id' => $row['parent_id']])->one();
			if ($original === null || (int) $original->status === AffiliateCommission::STATUS_CANCELLED) {
				// Nothing to offset (yet) — reconsidered on the next run.
				return false;
			}
			$percentage = $original->percentage;
			$affiliateId = $original->affiliate_id;
		} elseif (!$this->isWithinWindow($row['commission_months'], $row['subscriber_created_at'], $earnedAt)) {
			return false;
		}

		$net = static::netAmount(abs((float) $row['amount']), $row['vat']);
		if ($net == 0) {
			return false;
		}
		$base = $isRectify ? -$net : $net;

		$commission = new AffiliateCommission();
		$commission->affiliate_id = $affiliateId;
		$commission->invoice_id = $row['id'];
		$commission->subscriber_id = $row['subscriber_id'];
		$commission->type = (int) $row['type'];
		$commission->base_amount = $base;
		$commission->currency = $row['currency'];
		$commission->percentage = $percentage;
		$commission->amount = static::commissionAmount($base, $percentage);
		$commission->status = AffiliateCommission::STATUS_PENDING;
		$commission->earned_at = $earnedAt;
		$commission->payable_at = (new DateTime($earnedAt))
			->modify('+' . $this->getHoldDays() . ' days')
			->format('Y-m-d H:i:s');

		try {
			if (!$commission->save()) {
				Yii::error(['message' => 'Affiliate commission could not be saved.', 'invoice' => $commission->uuidOf('invoice_id'), 'errors' => $commission->getErrors()], __METHOD__);
				return false;
			}
		} catch (IntegrityException $e) {
			// A concurrent run created it first: the unique key did its job.
			return false;
		}
		return true;
	}

	/**
	 * Whether an invoice paid at `$eventAt` is within the affiliate's commission window.
	 *
	 * @param int|string|null $months null = lifetime
	 * @param string|null $signupAt subscriber.created_at
	 * @param string $eventAt
	 * @return bool
	 */
	protected function isWithinWindow($months, $signupAt, $eventAt)
	{
		if ($months === null || $months === '' || $signupAt === null || $signupAt === '') {
			return true;
		}
		$limit = (new DateTime($signupAt))->modify('+' . (int) $months . ' months');
		return new DateTime($eventAt) < $limit;
	}

	/**
	 * @param string[] $ids raw bytes
	 * @param int $status
	 * @return int
	 */
	protected function setStatus(array $ids, $status)
	{
		if (!$ids) {
			return 0;
		}
		return AffiliateCommission::updateAll(['status' => $status, 'updated_at' => $this->getNow()], ['id' => $ids]);
	}

	/**
	 * The raw ids among `$ids` that match `$condition`.
	 *
	 * @param string[] $ids raw bytes or canonical uuids
	 * @param array $condition
	 * @return string[]
	 */
	protected function findIds(array $ids, array $condition)
	{
		if (!$ids) {
			return [];
		}
		return AffiliateCommission::find()
			->select('id')
			->where(['id' => array_values($ids)])
			->andWhere($condition)
			->column();
	}

	/**
	 * @return string
	 */
	protected function getNow()
	{
		return $this->now ?: date('Y-m-d H:i:s');
	}

	/**
	 * @return int
	 */
	protected function getHoldDays()
	{
		return $this->holdDays !== null ? max(0, (int) $this->holdDays) : AffiliateSettings::getHoldDays();
	}
}
