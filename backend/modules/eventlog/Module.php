<?php

namespace backend\modules\eventlog;

use Yii;
use yii\base\InvalidConfigException;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
	/**
	 * @var string The view file name used to render the log differences.
	 * The controller does not require to declare a specific action to render this view.
	 * But the view file must exist to each controller view path in order to be rendered by this module.
	 */
	public $viewName = 'event-log';


	/**
	 * @inheritdoc
	 * @throws \yii\base\InvalidConfigException
	 */
	public function init()
	{
		parent::init();

		if (empty($this->viewName)) {
			throw new InvalidConfigException('The "viewName" property must be set as a non-empty string.');
		}

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
			'visible' => (bool) Yii::$app->settings->get('enableEventLogs'),
			'roles' => ['viewEventLog'],
			'icon' => 'icon-hourglass',
			'label' => Yii::t('common', 'Event Logs'),
			'url' => ['/eventlog-manager/event-log/index'],
		];
	}
}
