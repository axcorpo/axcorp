<?php

namespace common\components;

use common\helpers\UuidHelper;

/**
 * UrlManager that converts raw BINARY(16) UUID parameters to their canonical UUID
 * string form when building URLs.
 *
 * Models with a BINARY(16) primary key expose raw bytes via `$model->id`, so the many
 * existing `Url::to(['update', 'id' => $model->id])` calls would otherwise produce
 * percent-encoded binary garbage that also fails to match the pretty URL rules.
 * Normalizing here keeps every call site unchanged and yields readable URLs like
 * `/workspace/0190f4d6-8d4a-7a2c-9f35-3d4c1e7a9b12/update`. Raw UUID bytes are never
 * valid UTF-8, while any real parameter value is, so only genuine binary is converted.
 */
class UuidUrlManager extends \codemix\localeurls\UrlManager
{
	/**
	 * @inheritdoc
	 */
	public function createUrl($params)
	{
		return parent::createUrl($this->normalizeUuidParams($params));
	}

	/**
	 * Recursively converts raw 16-byte UUID values to canonical UUID strings.
	 *
	 * @param mixed $params
	 * @return mixed
	 */
	protected function normalizeUuidParams($params)
	{
		if (is_array($params)) {
			foreach ($params as $key => $value) {
				$params[$key] = $this->normalizeUuidParams($value);
			}
			return $params;
		}
		if (UuidHelper::isBytes($params) && !mb_check_encoding($params, 'UTF-8')) {
			return UuidHelper::fromBytes($params);
		}
		return $params;
	}
}
