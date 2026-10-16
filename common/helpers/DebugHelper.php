<?php

namespace common\helpers;

use Yii;

class DebugHelper
{
	/**
	 * Shows ActiveRecord Query RawSQL.
	 *
	 * @param $ar
	 */
	public static function showQuery($ar)
	{
		$query = $ar->prepare(Yii::$app->db->queryBuilder)->createCommand()->rawSql;

		echo '<h4>QUERY</h4>';
		echo '<code>';
		print_r($query);
		echo '</code>';

		echo '<br/><br/>';

		echo '<h4>DATA</h4>';
		echo '<pre>';
		print_r($ar->all());
		echo '</pre>';

		die();
	}

	/**
	 * Prints a formatted data.
	 *
	 * @param $data
	 * @param bool $shouldDie
	 */
	public static function prettyPrint($data, $shouldDie = false)
	{
		echo '<pre>';
		print_r($data);
		echo '</pre>';
		if ($shouldDie) {
			die();
		}
	}
}