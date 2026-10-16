<?php

namespace backend\modules\subscriber\modules\report;

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
			'roles' => ['viewSubscriber'],
			'icon' => 'fa fa-pie-chart',
			'label' => Yii::t('common', 'Reports'),
			'url' => '#',
			'items' => [
				[
					'roles' => ['viewSubscriber'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Subscriptions'),
					'url' => ['/subscriber-manager/report-manager/report/subscription'],
				],
				[
					'roles' => ['viewSubscriber'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Monthly Subscriptions'),
					'url' => ['/subscriber-manager/report-manager/report/subscription-monthly'],
				],
				[
					'roles' => ['viewSubscriber'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Subscriptions Statuses'),
					'url' => ['/subscriber-manager/report-manager/report/subscription-status'],
				],
				[
					'roles' => ['viewSubscriber'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Subscribers'),
					'url' => ['/subscriber-manager/report-manager/report/subscriber'],
				],
			],
		];
	}
}
