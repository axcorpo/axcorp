<?php

namespace backend\modules\helpdesk;

use common\models\SupportTicket;
use Yii;
use yii\helpers\Html;

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
		$badge = [];

		$supportTicketBadge = [];
		if (Yii::$app->user->can('viewHelpdeskSupportTicket')) {
			if ($unseenSupportTickets = SupportTicket::find()->andWhere(['seen' => SupportTicket::NO])->count('id')) {
				$supportTicketBadge = [
					'value' => $unseenSupportTickets,
					'type' => 'success',
				];
				$badge = $supportTicketBadge;
			}
		}

		return [
			'roles' => ['viewHelpdeskSupportTicket', 'viewHelpdeskSupportTicketDepartment', 'viewHelpdeskSupportTicketPriority', 'viewHelpdeskSupportTicketStatus'],
			'icon' => 'fa fa-life-ring',
			'badge' => $badge,
			'label' => Yii::t('common', 'Helpdesk'),
			'url' => '#',
			'items' => [
				[
					'roles' => ['viewHelpdeskSupportTicket'],
					'icon' => 'fa fa-circle-o',
					'badge' => $supportTicketBadge,
					'label' => Yii::t('common', 'Support Tickets'),
					'url' => ['/helpdesk-manager/support-ticket/index'],
				],
				[
					'roles' => ['viewHelpdeskSupportTicketDepartment', 'viewHelpdeskSupportTicketPriority', 'viewHelpdeskSupportTicketStatus'],
					'icon' => 'fa fa-list',
					'label' => Yii::t('common', 'Nomenclature'),
					'url' => '#',
					'items' => [
						[
							'roles' => ['viewHelpdeskSupportTicketDepartment'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Departments'),
							'url' => ['/helpdesk-manager/support-ticket-department/index'],
						],
						[
							'roles' => ['viewHelpdeskSupportTicketPriority'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Priorities'),
							'url' => ['/helpdesk-manager/support-ticket-priority/index'],
						],
						[
							'roles' => ['viewHelpdeskSupportTicketStatus'],
							'icon' => 'fa fa-circle-o',
							'label' => Yii::t('common', 'Statuses'),
							'url' => ['/helpdesk-manager/support-ticket-status/index'],
						],
					],
				],
			],
		];
	}
}
