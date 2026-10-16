<?php
/* @var $this yii\web\View */
/* @var $model common\models\Package */

use common\models\Feature;
use common\models\PackageFeature;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use yii\helpers\ArrayHelper;

/** @var PackageFeature[] $packageFeatures */
$packageFeatures = ArrayHelper::index($model->packageFeatures, 'name');
$featureModuleLabels = FeatureModule::getModuleLabels();
$featureLabels = Feature::getFeatureLabels();
?>

<table class="table table-bordered table-condensed">
	<tbody>
	<tr>
		<td class="col-autowidth"><?= $featureLabels[Feature::WORKSPACES] ?></td>
		<td><?= $packageFeatures[Feature::WORKSPACES]->value ?: 0 ?></td>
	</tr>
	<tr>
		<td class="col-autowidth"><?= $featureLabels[Feature::USERS] ?></td>
		<td><?= $packageFeatures[Feature::USERS]->value ?: 0 ?></td>
	</tr>
	</tbody>
</table>
