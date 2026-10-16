<?php

namespace backend\modules\nomenclature;

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
                'viewPackage', 'viewActivityDomain', 'viewSource', 'viewDocumentSeries', 'viewEmailTemplate', 'viewSmsTemplate', 'viewInvoiceTemplate',
                'manageSubscriberStatusCategory', 'manageSubscriberStatus',
            ],
			'icon' => 'fa fa-list',
			'label' => Yii::t('common', 'Nomenclature'),
			'url' => '#',
			'items' => [
				[
					'roles' => ['viewPackage'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Packages'),
					'url' => ['/nomenclature-manager/package/index'],
				],
				[
					'roles' => ['viewDocumentSeries'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Documents Series'),
					'url' => ['/nomenclature-manager/document-series/index'],
				],
				[
					'roles' => ['viewUnitOfMeasure'],
					'icon' => 'fa fa-circle-o',
					'label' => Yii::t('common', 'Unit Of Measures'),
					'url' => ['/nomenclature-manager/unit-of-measure/index'],
				],
				[
					'roles' => ['viewEmailTemplate', 'viewSmsTemplate', 'viewInvoiceTemplate'],
					'icon' => 'fa fa-file-o',
					'label' => Yii::t('common', 'Templates'),
					'url' => '#',
					'items' => [
						[
							'roles' => ['viewEmailTemplate'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Email'),
							'url' => ['/nomenclature-manager/email-template/index'],
						],
						[
							'roles' => ['viewInvoiceTemplate'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Invoice'),
							'url' => ['/nomenclature-manager/invoice-template/index'],
						],
					],
				],
			],
		];
	}
}
