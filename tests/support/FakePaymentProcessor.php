<?php

namespace tests\support;

/**
 * Test double for a payment processor (Stripe/Twispay): findTransactions() returns
 * whatever the test configured, so the recurring-payment flow can be exercised
 * without touching a real payment API.
 */
class FakePaymentProcessor
{
	const TRANSACTION_STATUS_SUCCEEDED = 'succeeded';

	/**
	 * @var array Transaction objects (->id, ->status) returned to the caller.
	 */
	public $transactions = [];

	/**
	 * @var array The filters received on the last call, for assertions.
	 */
	public $lastFilters = [];

	/**
	 * @param array $filters
	 * @return array
	 */
	/**
	 * @var array What findSubscriptionRenewal() reports: `period` (`start`/`end` UTC
	 * timestamps) and `invoice` (the processor's latest invoice); empty = unreachable.
	 */
	public $renewal = [];

	public $lastRenewalSubscriptionId;

	public function findTransactions($filters)
	{
		$this->lastFilters = $filters;
		return $this->transactions;
	}

	public function findSubscriptionRenewal($subscriptionId)
	{
		$this->lastRenewalSubscriptionId = $subscriptionId;
		return $this->renewal;
	}

	/**
	 * @var array What findSubscriptionUpcomingPeriod() reports (`start`/`end` UTC timestamps); empty = unknown.
	 */
	public $upcomingPeriod = [];

	public $lastUpcomingSubscriptionId;

	public function findSubscriptionUpcomingPeriod($subscriptionId)
	{
		$this->lastUpcomingSubscriptionId = $subscriptionId;
		return $this->upcomingPeriod;
	}
}
