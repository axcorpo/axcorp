<?php
/* @var $this \yii\web\View */
/* @var $content string */

use tws\helpers\Url;
use yii\helpers\Html;

\frontend\assets\AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<?= $this->render('@app/views/layouts/_begin', ['bodyAttributes' => ['class' => 'layout-account']]) ?>

<?php $this->params['breadcrumbs'] ?: $this->params['breadcrumbs'][] = Html::encode($this->title); ?>
<?= $this->render('@app/views/layouts/_page-title', ['title' => $this->title]) ?>

<!-- Main Content -->
<div class="main-content">
	<section class="section-account tf-spacing-1">
		<div class="container">
			<div class="row">
				<div class="col-lg-3 col-md-4">
					<div class="account-sidebar">
						<div class="account-user d-flex align-items-center">
							<img src="<?= Yii::$app->user->identity->imageUrl ?: Url::to("@web/img/img-placeholder-user.png") ?>" class="account-user__avatar" alt="<?= Yii::$app->user->identity->shortName ?>">
							<h5 class="account-user__name mb-0"><?= Yii::$app->user->identity->shortName ?></h5>
						</div>
						<?php if ($navMenu = \common\models\Menu::findDefaultSidebarMenu()): ?>
							<?= \common\widgets\sitemenu\SiteMenu::widget([
									'options' => [
											'class' => 'nav nav-pills nav-justified flex-column mb-0',
											'id' => 'pills-tab',
											'role' => 'tablist',
									],
									'activateParents' => true,
									'encodeLabels' => false,
									// Subscriber pages for subscribers, affiliate pages for affiliates (Module::filterMenuItems()).
									'items' => $this->context->module->filterMenuItems($navMenu->getNestedItems(null, \common\models\MenuItem::STATUS_ACTIVE), Yii::$app->user->identity),
							]) ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="col-lg-9 col-md-8 col-12 mt-4 mt-md-0">
					<div class="tab-content account-content" id="pills-tabContent">

						<?= $content ?>

					</div>
				</div>
			</div>
		</div>
	</section>
</div>
<!-- ./Main Content -->

<?= $this->render('@app/views/layouts/_end') ?>
<?php $this->endPage() ?>
