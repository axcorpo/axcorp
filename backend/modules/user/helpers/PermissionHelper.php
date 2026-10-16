<?php

namespace backend\modules\user\helpers;

use yii\helpers\Html;

class PermissionHelper
{
	/**
	 * Extracts permissions as a single dimensional array form a deep nested source array.
	 *
	 * @param array $source
	 * @return array
	 */
	public static function extractPermissions($source)
	{
		$permissions = [];

		foreach ($source as $key => $val) {
			if (is_array($val)) {
				$permissions = array_merge($permissions, self::extractPermissions($val));
			} elseif ($key !== 'heading' && $key !== 'items') {
				$permissions[] = $key;
			}
		}

		return $permissions;
	}

	/**
	 * Renders a permission checkbox list.
	 *
	 * @param \backend\widgets\ActiveForm $form
	 * @param $model
	 * @param $i
	 * @param $items
	 * @return mixed
	 */
	protected static function renderCheckboxList($form, $model, $i, $items)
	{
		return $form->field($model, 'permissions', [
			'options' => [
				'class' => 'form-group m0',
			],
		])->inline(true)->checkboxList($items, [
			'id' => null,
			'unselect' => null,
			'itemOptions' => [
				'data' => [
					'mark-all-child' => ".check-{$i}",
				],
				'labelOptions' => [
					'class' => 'mt-checkbox mt-checkbox-outline mt-checkbox-width-md',
				],
			],
		])->label(false);
	}

	/**
	 * Renders a grouped permission checkboxes list.
	 *
	 * @param \backend\widgets\ActiveForm $form
	 * @param $model
	 * @param $i
	 * @param $item
	 * @return string
	 */
	protected static function renderItem($form, $model, $i, $item)
	{
		$lines = [];

		$lines[] = Html::beginTag('fieldset', ['class' => 'fieldset']);
		$lines[] = Html::tag('legend', $item['heading']);
		$lines[] = self::renderCheckboxList($form, $model, $i, $item['items']);
		$lines[] = Html::endTag('fieldset');

		return implode("\n", $lines);
	}

	/**
	 * Renders the permissions checkboxes list.
	 *
	 * @param \backend\widgets\ActiveForm $form
	 * @param $model
	 * @param $i
	 * @param $data
	 * @return string
	 */
	public static function renderItems($form, $model, $i, $data)
	{
		$lines = [];

		if (isset($data['groups'])) {
			foreach ($data['groups'] as $key => $val) {
				$menu = [];

				if (isset($val['groups'])) {
					$menu[] = Html::beginTag('fieldset', ['class' => 'fieldset']);
					$menu[] = Html::tag('legend', $val['heading']);
					$menu[] = self::renderItems($form, $model, $i, $val);
					$menu[] = Html::endTag('fieldset');
				} else {
					$menu[] = self::renderItem($form, $model, $i, $val);
				}

				$lines[] = implode("\n", $menu);
			}
		} else {
			$lines[] = self::renderCheckboxList($form, $model, $i, $data['items']);
		}

		return implode("\n", $lines);
	}

	/**
	 * Filters permissions list by name.
	 *
	 * @param array $permissionsList
	 * @param array $permissionsNames
	 * @return array
	 */
	public static function filterPermissionsByName($permissionsList, $permissionsNames)
	{
		$result = [];

		foreach ($permissionsList as $key => $val) {
			if (isset($val['groups'])) {
				$result[$val['heading']] = self::filterPermissionsByName($val['groups'], $permissionsNames);
			} else {
				$perms = array_intersect_key($val['items'], array_flip($permissionsNames));
				if (!empty($perms)) {
					$result[$val['heading']] = $perms;
				} else {
					unset($result[$val['heading']]);
				}
			}
		}

		return array_filter($result);
	}

	/**
	 * Gets all permissions as a HTML list.
	 *
	 * @param array $permissionsList
	 * @return string
	 */
	protected static function getListItems($permissionsList)
	{
		$content = [];

		foreach ($permissionsList as $key => $val) {
			$content[] = Html::beginTag('fieldset', ['class' => 'fieldset']);
			$content[] = Html::tag('legend', $key);
			if (count(array_filter($val, 'is_array'))) {
				$content[] = self::getListItems($val);
			} else {
				$val = array_map(function ($item) {
					return Html::tag('span', $item, ['class' => 'badge badge-outline badge-roundless']);
				}, $val);
				$content[] = Html::tag('div', implode('', $val), ['class' => 'white-space-normal']);
			}
			$content[] = Html::endTag('fieldset');
		}

		return implode("\n", $content);
	}

	/**
	 * List all permissions as HTML grouped list.
	 *
	 * @param array $permissionsList
	 * @param array $permissionsNames
	 * @return string
	 */
	public static function listItems($permissionsList, $permissionsNames)
	{
		return self::getListItems(self::filterPermissionsByName($permissionsList, $permissionsNames));
	}
}
