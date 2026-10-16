<?php

namespace tests\support;

use Yii;
use yii\base\Component;

/**
 * Stands in for the `instance` component: every app alias (`@frontend`, ...) resolves
 * to the test application itself, so URLs built "for the frontend" still build.
 */
class FakeInstance extends Component
{
	public function get($app)
	{
		return Yii::$app;
	}
}
