<?php
namespace console\controllers;

use common\models\User;
use DateTime;
use Yii;
use yii\console\Controller;

class UserController extends Controller
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * Deletes the unconfirmed user account accounts.
	 *
	 * @return bool|int
	 */
	public function actionDeleteUnconfirmedAccounts()
	{
		try {
			/** @var User[] $users */
			$users = User::find()
				->select(['id'])
				->andWhere(['IS NOT', 'signup_token', null])
				->andWhere([
					'status' => User::STATUS_INACTIVE,
					'deleted' => User::NO,
				])
				->all();
			$userSignupExpiration = Yii::$app->settings->get('userSignupExpiration');
			$currentDate = new DateTime();
			$count = 0;

			foreach ($users as $user) {
				$expirationDate = (new DateTime($user->created_at))->modify("+{$userSignupExpiration} seconds");
				if ($currentDate >= $expirationDate) {
					$user->delete(true);
					$count++;
				}
			}

			$this->stdout("[" . date("Y-m-d H:i:s") . "] Deleted {$count} user unconfirmed account(s).\n");

			return $count;
		} catch (\Exception $e) {
			$this->stderr($e->getMessage());
			return false;
		} catch (\Throwable $e) {
			$this->stderr($e->getMessage());
			return false;
		}
	}
}
