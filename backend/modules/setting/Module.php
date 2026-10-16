<?php

namespace backend\modules\setting;

use Yii;

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
			'roles' => [
				'updateGeneralSetting', 'updateEmailSetting', 'updateContactSetting', 'updateSocialNetworksSetting',
				'updateSeoSetting', 'updatePaymentSetting', 'viewLanguage', 'viewCurrency', 'clearCacheSetting',
				'updateAffiliateSetting',
			],
			'icon' => 'icon-settings',
			'label' => Yii::t('common', 'Settings'),
			'url' => '#',
			'items' => [
				[
					'roles' => ['updateGeneralSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'General'),
					'url' => ['/setting-manager/setting/index'],
				],
				[
					'roles' => ['updateEmailSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Email'),
					'url' => ['/setting-manager/setting/email'],
				],
				[
					'roles' => ['updateSmsSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'SMS'),
					'url' => ['/setting-manager/setting/sms'],
				],
				[
					'roles' => ['updatePaymentSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Payment'),
					'url' => ['/setting-manager/setting/payment'],
				],
				[
					'roles' => ['updateCommercialSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Commercial'),
					'url' => ['/setting-manager/setting/commercial'],
				],
				[
					'roles' => ['updateAffiliateSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Affiliates'),
					'url' => ['/setting-manager/setting/affiliate'],
				],
				[
					'roles' => ['updateSeoSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'SEO'),
					'url' => ['/setting-manager/setting/seo'],
				],
				[
					'roles' => ['updateSocialNetworkSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Social Networks'),
					'url' => ['/setting-manager/setting/social-network'],
				],
				[
					'roles' => ['updateContactSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Contact'),
					'url' => ['/setting-manager/setting/contact'],
				],
				[
					'roles' => ['viewLanguage'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Languages'),
					'url' => ['/setting-manager/language-manager/language/index'],
				],
				[
					'roles' => ['viewCurrency'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Currencies'),
					'url' => ['/setting-manager/currency-manager/currency/index'],
				],
				[
					'roles' => ['viewIntegration'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Integrations'),
					'url' => ['/setting-manager/integration-manager/integration/index'],
				],
				[
					'roles' => ['updateScript'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Scripts'),
					'url' => ['/setting-manager/setting/script'],
				],
				[
					'roles' => ['clearCacheSetting'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Clear Cache'),
					'url' => ['/setting-manager/setting/clear-cache'],
					'linkOptions' => [
						'data' => [
							'method' => 'POST',
							'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
						],
					]
				],
			],
		];
	}
}
