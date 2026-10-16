<?php
/* @var $this yii\web\View */

use yii\helpers\Html;
use tws\helpers\Url;

$requestQueryParams = Yii::$app->request->getQueryParams();
if (empty($requestQueryParams['module'])) {
	$requestQueryParams['module'] = 'service';
}
Yii::$app->request->setQueryParams($requestQueryParams);

$wsModules = [
	'service' => Yii::t('common', 'Service'),
];
?>

<div class="tab-group">
	<ul class="nav nav-tabs nav-justified" role="tablist">
		<?php foreach ($wsModules as $wsModuleKey => $wsModuleLabel): ?>
			<li class="<?= $wsModuleKey == $requestQueryParams['module'] ? 'active' : '' ?>" role="presentation">
				<a href="<?= Url::to(['manage-workspace-data', 'module' => $wsModuleKey]) ?>" role="tab"><?= $wsModuleLabel ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
	<div class="tab-content">
		<?php foreach ($wsModules as $wsModuleKey => $wsModuleLabel): ?>
			<?php if ($wsModuleKey == $requestQueryParams['module']): ?>
				<div class="tab-pane active" role="tabpanel">
					<?= $this->render($wsModuleKey) ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
