<?php

namespace backend\modules\affiliate;

use common\services\AffiliateNotificationService;
use Yii;

/**
 * Admin side of the affiliate module (docs/afiliere.md): affiliates, the subscribers they
 * referred, commissions, payouts and reports. The money logic lives in common\services
 * (AffiliateCommissionService, AffiliatePayoutService); this module is only UI + access.
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		Yii::configure($this, require __DIR__ . '/config/main.php');
	}

	/**
	 * @inheritdoc
	 */
	public function bootstrap($app)
	{
		if ($app instanceof \yii\web\Application) {
			$app->getUrlManager()->addRules([$this->getUrlManagerRules()], false);
		}
		// Payout paid / invoice rejected e-mails; the payout actions all run in this app.
		AffiliateNotificationService::attachEventHandlers();
	}

	/**
	 * Gets the UrlManager component rules.
	 * Handles the children and submodules UrlManager component rules.
	 *
	 * @return array
	 */
	public function getUrlManagerRules()
	{
		// Children rules
		$rules = array_map(function ($rule) {
			if (isset($rule['class']) && $rule['class'] === 'yii\web\GroupUrlRule') {
				$rule['prefix'] = str_replace('<module>', $this->id, $rule['prefix']);
				$rule['routePrefix'] = str_replace('<module>', $this->id, $rule['routePrefix']);
			}
			return $rule;
		}, $this->urlManager->rules);

		// Submodules rules
		foreach ($this->modules as $moduleId => $module) {
			$module = $this->getModule($moduleId);
			if (method_exists($module, 'getUrlManagerRules')) {
				array_unshift($rules, $module->getUrlManagerRules());
			}
		}

		return [
			'class' => 'yii\web\GroupUrlRule',
			'prefix' => $this->module instanceof \yii\base\Application ? $this->id : "{$this->module->id}/{$this->id}",
			'rules' => $rules,
		];
	}

	/**
	 * Gets the nav menu items of this module.
	 *
	 * @return array
	 */
	public function getNavMenuItems()
	{
		return [
			'roles' => ['viewAffiliate', 'manageAffiliatePayout'],
			'icon' => 'fa fa-handshake-o',
			'label' => Yii::t('common', 'Affiliates'),
			'url' => '#',
			'items' => [
				[
					'roles' => ['viewAffiliate'],
					'icon' => 'fa fa-line-chart',
					'label' => Yii::t('common', 'Overview'),
					'url' => ['/affiliate-manager/report/dashboard'],
				],
				[
					'roles' => ['viewAffiliate'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Affiliates'),
					'url' => ['/affiliate-manager/affiliate/index'],
				],
				[
					'roles' => ['viewAffiliate'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Referred Subscribers'),
					'url' => ['/affiliate-manager/subscriber/index'],
				],
				[
					'roles' => ['viewAffiliate'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Commissions'),
					'url' => ['/affiliate-manager/commission/index'],
				],
				[
					'roles' => ['manageAffiliatePayout'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Affiliate Payouts'),
					'url' => ['/affiliate-manager/payout/index'],
				],
				[
					'roles' => ['viewAffiliate'],
					'icon' => 'fa fa-pie-chart',
					'label' => Yii::t('common', 'Reports'),
					'url' => '#',
					'items' => [
						[
							'roles' => ['viewAffiliate'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Statistics'),
							'url' => ['/affiliate-manager/report/statistics'],
						],
						[
							'roles' => ['viewAffiliate'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Leaderboard'),
							'url' => ['/affiliate-manager/report/leaderboard'],
						],
					],
				],
			],
		];
	}
}
