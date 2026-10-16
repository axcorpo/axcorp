<?php
/* @var $this yii\web\View */
/* @var $model common\models\Subscription */

use common\models\Feature;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use yii\helpers\ArrayHelper;

/** @var \common\models\SubscriptionFeature[] $subscriptionFeatures */
$subscriptionFeatures = ArrayHelper::index($model->subscriptionFeatures, 'name');
$featureModuleLabels = FeatureModule::getModuleLabels();
$featureLabels = Feature::getFeatureLabels();
?>

<table class="table table-bordered table-condensed">
	<tbody>
		<tr>
			<td class="col-autowidth"><?= $featureLabels[Feature::WORKSPACES] ?></td>
			<td><?= $subscriptionFeatures[Feature::WORKSPACES]->value ?: 0 ?></td>
		</tr>
	</tbody>
</table>
