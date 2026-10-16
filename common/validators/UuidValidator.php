<?php

namespace common\validators;

use common\helpers\UuidHelper;
use Yii;
use yii\validators\Validator;

/**
 * Validates that an attribute holds a canonical UUID string
 * (e.g. `0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12`).
 *
 * Raw 16-byte values are also accepted when [[allowBinary]] is enabled, so the
 * validator can be applied to attributes that already hold the DB representation.
 *
 * Usage in a model:
 * ```php
 * ['assistant_id', \common\validators\UuidValidator::class],
 * ```
 */
class UuidValidator extends Validator
{
	/**
	 * @var bool Whether raw 16-byte values are considered valid.
	 */
	public $allowBinary = true;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		if ($this->message === null) {
			$this->message = Yii::t('yii', '{attribute} is invalid.');
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function validateValue($value)
	{
		if (UuidHelper::isValid($value)) {
			return null;
		}
		if ($this->allowBinary && UuidHelper::isBytes($value) && !UuidHelper::isValid($value)) {
			return null;
		}
		return [$this->message, []];
	}
}
