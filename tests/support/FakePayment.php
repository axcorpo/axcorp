<?php

namespace tests\support;

use yii\base\Component;

/**
 * Test double for the tws\payment\Payment component: via() hands back a shared
 * FakePaymentProcessor regardless of the requested processor id.
 */
class FakePayment extends Component
{
	/**
	 * @var FakePaymentProcessor
	 */
	public $processor;

	public function init()
	{
		parent::init();
		if ($this->processor === null) {
			$this->processor = new FakePaymentProcessor();
		}
	}

	/**
	 * @param mixed $processor
	 * @return FakePaymentProcessor
	 */
	public function via($processor)
	{
		return $this->processor;
	}
}
