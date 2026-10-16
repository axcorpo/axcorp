<?php

namespace frontend\modules\account;

use common\models\Subscription;
use tws\helpers\Url;
use Yii;
use yii\helpers\Inflector;

class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
	/**
	 * @inheritdoc
	 */
	public $layout = 'main';

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
		Yii::setAlias("@{$this->id}", __DIR__);

		$app->i18n->translations[$this->id] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'basePath' => "@{$this->id}/messages",
			'forceTranslation' => true,
			'fileMap' => [
				$this->id => 'i18n.php',
			],
		];

		if ($app instanceof \yii\web\Application) {
			if ($urlRules = $this->buildUrlRules()) {
				$app->getUrlManager()->addRules([$urlRules], false);
			}
		}
	}

	/**
	 * Controllers open to both subscribers and affiliates (profile, billing company, help).
	 * The company page is where an affiliate fills in the billing data and IBAN.
	 *
	 * @var string[]
	 */
	public $sharedControllers = ['default', 'profile', 'company'];

	/**
	 * Controllers of the affiliate area; they also need the user's active `affiliate` row.
	 * Every controller listed in neither property is subscriber-only: those assume a
	 * subscriber row (invoices, subscriptions, workspaces, helpdesk, payment).
	 *
	 * @var string[]
	 */
	public $affiliateControllers = ['affiliate', 'affiliate-subscriber', 'affiliate-commission', 'affiliate-payout'];

	/**
	 * @inheritdoc
	 */
	public function beforeAction($action)
	{
		if (!Yii::$app->user->isGuest) {
			/** @var \common\models\User $user */
			$user = Yii::$app->user->identity;
			$isSubscriber = $user->getIsSubscriber();

			// Prevent access to this module if the current user is neither a subscriber nor an active affiliate
			if (!$isSubscriber && !$user->getIsAffiliate()) {
				if ($user->getHasPermissions() && Yii::$app->requestedRoute == 'account/profile/index') {
					return Yii::$app->response->redirect(Url::to(['/site/profile'], false, '@backend'))->send();
				}
				Yii::$app->session->setFlash('error', Yii::t('yii', 'You are not allowed to perform this action.'));
				return Yii::$app->response->redirect(['/site/index'])->send();
			}

			// A page of the other user type: send the user to their own area instead of
			// letting a subscriber-only controller dereference a missing subscriber row.
			if (($redirect = $this->getAccessRedirect($action->controller->id, $user)) !== null) {
				return Yii::$app->response->redirect($redirect)->send();
			}

			// Display workspace reminder
			if ($isSubscriber && !Yii::$app->session->has('isWorkspaceReminderVisible')) {
				$activeSubscriptionsQuery = $user->subscriber->getSubscriptions()->andWhere([
					'type' => [Subscription::TYPE_STANDARD, Subscription::TYPE_CUSTOM],
					'status' => Subscription::STATUS_ACTIVE,
				]);
				if ($activeSubscriptionsQuery->exists()) {
					if (!$user->subscriber->getWorkspaces()) {
						Yii::$app->session->set('isWorkspaceReminderVisible', false);
						Yii::$app->session->setFlash('info', Yii::t('frontend', 'It seems that you don\'t have any workspaces configured yet. Add at least one workspace in order to use the app.'));
					}
				}
			}
		}

		return parent::beforeAction($action);
	}

	/**
	 * Whether `$user` may use the given controller of this module: shared controllers need
	 * a subscriber or an active affiliate, affiliate controllers an active affiliate, all the
	 * others an active subscriber. A user who is both sees both sets.
	 *
	 * @param string $controllerId
	 * @param \common\models\User $user
	 * @return bool
	 */
	public function isControllerAllowed($controllerId, $user)
	{
		if (in_array($controllerId, $this->sharedControllers, true)) {
			return $user->getIsSubscriber() || $user->getIsAffiliate();
		}
		if (in_array($controllerId, $this->affiliateControllers, true)) {
			return $user->getIsAffiliate();
		}
		return $user->getIsSubscriber();
	}

	/**
	 * Where to send `$user` when they open a controller that is not theirs, null when it is.
	 *
	 * @param string $controllerId
	 * @param \common\models\User $user
	 * @return array|null a route
	 */
	public function getAccessRedirect($controllerId, $user)
	{
		if ($this->isControllerAllowed($controllerId, $user)) {
			return null;
		}
		return $user->getIsAffiliate() ? ["/{$this->id}/affiliate/index"] : ["/{$this->id}/profile/index"];
	}

	/**
	 * Drops from a nested sidebar menu (Menu::getNestedItems()) the items pointing to pages
	 * of this module that `$user` may not open, so subscribers do not see the affiliate
	 * pages and vice versa. Visibility follows [[isControllerAllowed()]] — the same rule as
	 * the access check — rather than a per-item setting that could drift from it. Items
	 * without a page, or with a page of another module, are kept.
	 *
	 * @param array $items
	 * @param \common\models\User $user
	 * @return array
	 */
	public function filterMenuItems(array $items, $user)
	{
		$result = [];
		foreach ($items as $item) {
			$model = $item['model'] ?? null;
			if (is_object($model) && !empty($model->page_id) && ($page = $model->page) !== null
				&& $page->module === $this->id && !$this->isControllerAllowed($page->controller, $user)) {
				continue;
			}
			if (!empty($item['items'])) {
				$item['items'] = $this->filterMenuItems($item['items'], $user);
			}
			$result[] = $item;
		}
		return $result;
	}

	/**
	 * Builds the rules for all pages of this module.
	 *
	 * @return array
	 */
	protected function buildUrlRules()
	{
		$pages = \common\models\Page::findPagesByModule($this->id);
		$urlRules = [
			[
				'pattern' => '',
				'route' => 'default/index',
			],
		];

		foreach ($pages as $page) {
			$pageTranslation = $page->getTranslation();

			// Custom routes
			if ($page->controller == 'profile') {
				$urlRules = array_merge($urlRules, $this->buildProfileUrlRules($page, $pageTranslation));
				continue;
			} elseif ($page->controller == 'workspace') {
				$urlRules = array_merge($urlRules, $this->buildWorkspaceUrlRules($page, $pageTranslation));
			} elseif ($page->controller == 'helpdesk') {
				$urlRules = array_merge($urlRules, $this->buildHelpdeskUrlRules($page, $pageTranslation));
			} elseif ($page->controller == 'payment') {
				$urlRules = array_merge($urlRules, $this->buildPaymentUrlRules($page, $pageTranslation));
				continue;
			}

			// Default routes
			$urlRules[] = [
				'pattern' => $pageTranslation->slug,
				'route' => "{$page->controller}/{$page->action}",
			];
			$urlRules[] = [
				'pattern' => "{$pageTranslation->slug}/<id>/<action>",
				'route' => "{$page->controller}/<action>",
			];
			$urlRules[] = [
				'pattern' => "{$pageTranslation->slug}/<action>",
				'route' => "{$page->controller}/<action>",
			];
		}

		return [
			'class' => 'yii\web\GroupUrlRule',
			'routePrefix' => $this->id,
			'prefix' => Inflector::slug(Yii::t($this->id, 'Account')),
			'rules' => $urlRules,
		];
	}

	/**
	 * Builds URL Rules for ProfileController.
	 *
	 * @param \common\models\Page $page
	 * @param \common\models\PageTranslation $pageTranslation
	 * @return array
	 */
	protected function buildProfileUrlRules($page, $pageTranslation)
	{
		$urlRules[] = [
			'pattern' => $pageTranslation->slug,
			'route' => "{$page->controller}/{$page->action}",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/upload-file",
			'route' => "{$page->controller}/upload-file",
		];

		return $urlRules;
	}

	/**
	 * Builds URL Rules for WorkspaceController.
	 *
	 * @param \common\models\Page $page
	 * @param \common\models\PageTranslation $pageTranslation
	 * @return array
	 */
	protected function buildWorkspaceUrlRules($page, $pageTranslation)
	{
		$urlRules[] = [
			'pattern' => $pageTranslation->slug,
			'route' => "{$page->controller}/{$page->action}",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/<id>/<action>",
			'route' => "{$page->controller}/<action>",
		];

		return $urlRules;
	}

	/**
	 * Builds URL Rules for SupportController.
	 *
	 * @param \common\models\Page $page
	 * @param \common\models\PageTranslation $pageTranslation
	 * @return array
	 */
	protected function buildHelpdeskUrlRules($page, $pageTranslation)
	{
		$urlRules[] = [
			'pattern' => "support-ticket/<id>/<action>",
			'route' => "support-ticket/<action>",
		];
		$urlRules[] = [
			'pattern' => "support-ticket/<action>",
			'route' => "support-ticket/<action>",
		];

        $urlRules[] = [
            'pattern' => "shipping/<id>/<action>",
            'route' => "shipping/<action>",
        ];
        $urlRules[] = [
            'pattern' => "shipping/<action>",
            'route' => "shipping/<action>",
        ];

        $urlRules[] = [
            'pattern' => "billing/<id>/<action>",
            'route' => "billing/<action>",
        ];
        $urlRules[] = [
            'pattern' => "billing/<action>",
            'route' => "billing/<action>",
        ];

        $urlRules[] = [
            'pattern' => "order/<id>/<action>",
            'route' => "order/<action>",
        ];
        $urlRules[] = [
            'pattern' => "order/<action>",
            'route' => "order/<action>",
        ];
		return $urlRules;
	}

	/**
	 * Builds URL Rules for PaymentController.
	 *
	 * @param \common\models\Page $page
	 * @param \common\models\PageTranslation $pageTranslation
	 * @return array
	 */
	protected function buildPaymentUrlRules($page, $pageTranslation)
	{
		$urlRules[] = [
			'pattern' => $pageTranslation->slug,
			'route' => "{$page->controller}/{$page->action}",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/" . Inflector::slug(Yii::t($this->id, 'Package')),
			'route' => "{$page->controller}/package",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/" . Inflector::slug(Yii::t($this->id, 'Subscription')),
			'route' => "{$page->controller}/subscription",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/" . Inflector::slug(Yii::t($this->id, 'Invoice')),
			'route' => "{$page->controller}/invoice",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/" . Inflector::slug(Yii::t($this->id, 'Features')),
			'route' => "{$page->controller}/features",
		];
		$urlRules[] = [
			'pattern' => "{$pageTranslation->slug}/" . Inflector::slug(Yii::t($this->id, 'Result')),
			'route' => "{$page->controller}/result",
		];

		return $urlRules;
	}
}
