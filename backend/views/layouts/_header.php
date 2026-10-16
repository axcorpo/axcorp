<?php
/* @var $this \yii\web\View */

use yii\helpers\Html;
use tws\helpers\Url;

$appLanguages = \common\models\Language::findAllLanguages();
$currentAppLanguage = $appLanguages[Yii::$app->language];
?>

<div class="page-header navbar navbar-fixed-top">
	<div class="page-header-inner">
		<div class="page-logo">
			<a href="/<?= mb_substr(Yii::$app->language, 0, 2) ?>" target="_blank">
				<img class="logo-default" src="<?= Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt')) ?: Url::to('@web/img/logo-alt.png') ?>" alt="<?= Yii::$app->name ?>">
			</a>
			<div class="menu-toggler sidebar-toggler">
				<span></span>
			</div>
		</div>
		<a href="javascript:void(0);" class="menu-toggler responsive-toggler" data-toggle="collapse" data-target=".navbar-collapse">
			<span></span>
		</a>
		<div class="top-menu">
			<ul class="nav navbar-nav pull-right">
				<?php if (Yii::$app->settings->get('enableDocumentation')): ?>
					<li class="link">
						<?= Html::a('<span><i class="fa fa-book"></i> ' . Yii::t('common', 'Manual'). '</span>', '/documentation', ['target' => '_blank']) ?>
					</li>
				<?php endif; ?>
				<?php if (Yii::$app->user->can('viewNotification')): ?>
					<?= backend\modules\notification\widgets\notification\Notification::widget([
						'id' => 'header_notification_bar',
						'options' => [
							'tagName' => 'li',
							'class' => 'dropdown dropdown-extended dropdown-notification',
						],
						'clientOptions' => [
							'url' => Url::to(['/notification-manager/notification/list']),
							'refreshInterval' => 25 * 1000,
						],
					]) ?>
				<?php endif; ?>

				<?php if (count($appLanguages) > 1): ?>
					<li class="dropdown dropdown-language">
						<a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
							<img src="<?= Url::to("@web/img/flags/{$currentAppLanguage->country}.png") ?>" alt="<?= Yii::$app->language ?>">
							<span class="langname"><?= mb_strtoupper($currentAppLanguage->language) ?></span>
							<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu dropdown-menu-default">
							<?php foreach ($appLanguages as $appLanguage) : ?>
								<?php if ($appLanguage->language_id != Yii::$app->language) : ?>
									<?php
									$currentRoute = Yii::$app->request->getQueryParams();
									$currentRoute['language'] = $appLanguage->language_id;
									array_unshift($currentRoute, substr(Yii::$app->requestedRoute, 0, 1) == '/' ? Yii::$app->requestedRoute : '/' . Yii::$app->requestedRoute);
									?>
									<li>
										<a href="<?= Url::to($currentRoute) ?>">
											<img src="<?= Url::to("@web/img/flags/{$appLanguage->country}.png") ?>" alt="<?= $appLanguage->name ?>">
											<span><?= $appLanguage->name ?></span>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>

				<li class="dropdown dropdown-user">
					<a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
						<img class="img-circle" src="<?= Yii::$app->user->identity->imageUrl ?: Url::to('@web/img/img-placeholder-user.png') ?>" alt="<?= Yii::$app->user->identity->fullName ?>">
						<span class="username username-hide-on-mobile"><?= Yii::$app->user->identity->fullName ?: Yii::$app->user->identity->email ?></span>
						<i class="fa fa-angle-down"></i>
					</a>
					<ul class="dropdown-menu dropdown-menu-default">
						<?php if (Yii::$app->user->can('viewNotification')): ?>
							<li>
								<a href="<?= Url::to(['/notification-manager/notification/index']) ?>">
									<i class="icon-bell"></i> <?= Yii::t('common', 'Notifications') ?>
								</a>
							</li>
						<?php endif; ?>
						<li>
							<a href="<?= Url::to(['/profile/index']) ?>">
								<i class="icon-user"></i> <?= Yii::t('common', 'My Profile') ?>
							</a>
						</li>
						<li class="divider"></li>
						<li>
							<?= Html::a('<i class="icon-key"></i> ' . Yii::t('common', 'Log Out'), ['/site/logout'], [
								'data' => [
									'method' => 'POST',
									'confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							]) ?>
						</li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</div>
