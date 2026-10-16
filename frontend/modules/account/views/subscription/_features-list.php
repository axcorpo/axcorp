<?php
/* @var $this yii\web\View */
/* @var $model common\models\Subscription */

use common\models\Feature;
use common\models\SubscriptionFeature;
use yii\helpers\Html;

// Every feature row of this subscription, as stored in subscription_feature at
// purchase time (package features) or bought later (add-ons).
$featureLabels = Feature::getFeatureLabels();
$subscriptionFeatures = $model->getSubscriptionFeatures()
	->andWhere(['deleted' => SubscriptionFeature::NO])
	->orderBy(['name' => SORT_ASC])
	->all();
?>

<table class="table table-bordered table-sm mb-0">
	<tbody>
		<?php foreach ($subscriptionFeatures as $subscriptionFeature): ?>
			<tr>
				<td class="col-autowidth"><?= Html::encode($featureLabels[$subscriptionFeature->name] ?? $subscriptionFeature->name) ?></td>
				<td>
					<?= Html::encode($subscriptionFeature->value ?: 0) ?>
					<?php if ($subscriptionFeature->price > 0): ?>
						x <?= Yii::$app->formatter->asCurrency($subscriptionFeature->price, $subscriptionFeature->currency ?: $model->currency) ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
