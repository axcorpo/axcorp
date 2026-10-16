<?php
/* @var $this yii\web\View */
/* @var $model common\models\Invoice */

use common\models\Invoice;
use common\models\Item;
use common\models\PaymentMetadata;
use common\models\Subscription;
use common\models\SubscriptionFeature;

$invoiceDetails = $model->getUnserializedValue('details');

/** @var Item $subscriptionItem */
$subscriptionItem = $model->getItems()->andWhere(['type' => Item::TYPE_SUBSCRIPTION])->limit(1)->one();
$subscription = $subscriptionItem->subscription;
?>

<?php if ($subscription->type == Subscription::TYPE_FEATURE): ?>
	<?php $itemDetails = $subscriptionItem->getUnserializedValue('details'); ?>
	<table style="width: 100%; border-collapse: collapse; border: 0;">
		<thead>
			<tr>
				<th style="padding: 10px 0; text-align: left;" colspan="4"><?= Yii::t('common', 'Features for {0} subscription', ["#{$subscription->parent->code} ({$subscription->parent->package->translation->name})"]) ?></th>
			</tr>
			<tr>
				<th style="padding: 5px 10px 5px 0; text-align: left;"><?= Yii::t('label', 'Feature') ?></th>
				<th style="padding: 5px 10px 5px 10px; text-align: right;"><?= Yii::t('label', 'Quantity') ?></th>
				<th style="padding: 5px 10px 5px 10px; text-align: right;"><?= Yii::t('label', 'Price') ?></th>
				<th style="padding: 5px 0 5px 10px; text-align: right;"><?= Yii::t('label', 'Total Price') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php /** @var SubscriptionFeature $subscriptionFeature */ ?>
			<?php foreach ($subscription->getSubscriptionFeatures()->each() as $subscriptionFeature): ?>
				<tr>
					<td style="padding: 5px 10px 5px 0; border-top: 1px solid #ddd; text-align: left;"><?= $subscriptionFeature->getFormattedName() ?></td>
					<td style="padding: 5px 10px 5px 10px; border-top: 1px solid #ddd; text-align: right;"><?= $subscriptionFeature->value ?></td>
					<td style="padding: 5px 10px 5px 10px; border-top: 1px solid #ddd; text-align: right;"><?= Yii::$app->formatter->asCurrency($subscriptionFeature->price, $subscription->currency) ?></td>
					<td style="padding: 5px 0 5px 10px; border-top: 1px solid #ddd; text-align: right;"><?= Yii::$app->formatter->asCurrency(($subscriptionFeature->value * $subscriptionFeature->price), $subscription->currency) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th style="padding: 10px 10px 5px 10px; border-top: 1px solid #ddd; text-align: right;" colspan="3"><?= Yii::t('label', 'Subtotal') ?></th>
				<td style="padding: 10px 0 5px 10px; border-top: 1px solid #ddd; text-align: right;">
					<strong><?= Yii::$app->formatter->asCurrency($subscription->price, $model->currency) ?></strong>
				</td>
			</tr>
			<tr>
				<th style="padding: 5px 10px 5px 10px; text-align: right; font-size: 16px;" colspan="3"><?= Yii::t('label', 'Total') ?></th>
				<td style="padding: 5px 0 5px 10px; text-align: right; font-size: 16px;">
					<strong><?= Yii::$app->formatter->asCurrency($subscription->price, $model->currency) ?></strong>
				</td>
			</tr>
		</tfoot>
	</table>
<?php else: ?>
	<table style="width: 100%; border-collapse: collapse; border: 0;">
		<?php $totalAmount = $model->getTotalAmount(); ?>
		<thead>
			<tr>
				<th style="padding: 5px 10px 5px 0; text-align: left;"><?= Yii::t('label', 'Subscription') ?></th>
				<th style="padding: 5px 10px 5px 10px; text-align: left;"><?= Yii::t('label', 'Period') ?></th>
				<th style="padding: 5px 0 5px 10px; text-align: right;"><?= Yii::t('label', 'Price') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php /** @var Item $item */ ?>
			<?php foreach ($model->getItems()->active()->deleted(false)->each() as $item): ?>
				<?php $itemDetails = $item->getUnserializedValue('details'); ?>
				<tr>
					<td style="padding: 5px 10px 5px 0; border-top: 1px solid #ddd; text-align: left;"><?= $itemDetails['subscription'] ?></td>
					<td style="padding: 5px 10px 5px 10px; border-top: 1px solid #ddd; text-align: left;"><?= Yii::$app->formatter->asDatetime($itemDetails['start_at']) ?> &mdash; <?= Yii::$app->formatter->asDatetime($itemDetails['end_at']) ?></td>
					<td style="padding: 5px 0 5px 10px; border-top: 1px solid #ddd; text-align: right;"><?= Yii::$app->formatter->asCurrency($item->price, $item->currency) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th style="padding: 10px 0 5px 10px; border-top: 1px solid #ddd; text-align: right;" colspan="2"><?= Yii::t('label', 'Subtotal') ?></th>
				<td style="padding: 10px 0 5px 10px; border-top: 1px solid #ddd; text-align: right;">
					<strong><?= Yii::$app->formatter->asCurrency($totalAmount, $model->currency) ?></strong>
				</td>
			</tr>
			<tr>
				<th style="padding: 5px 0 5px 10px; text-align: right; font-size: 16px;" colspan="2"><?= Yii::t('label', 'Total') ?></th>
				<td style="padding: 5px 0 5px 10px; text-align: right; font-size: 16px;">
					<strong><?= Yii::$app->formatter->asCurrency($totalAmount, $model->currency) ?></strong>
				</td>
			</tr>
		</tfoot>
	</table>
<?php endif; ?>

<?php if ($model->status == Invoice::STATUS_PAID): ?>
	<br/><br/>

	<table style="width: 100%; border-collapse: collapse; border: 0;">
		<thead>
			<tr>
				<th style="padding: 5px 10px 5px 0; text-align: left; vertical-align: top;"><?= Yii::t('label', 'Transaction Date') ?></th>
				<th style="padding: 5px 10px 5px 10px; text-align: left; vertical-align: top;"><?= Yii::t('label', 'Transaction ID') ?></th>
				<th style="padding: 5px 0 5px 10px; text-align: left; vertical-align: top;"><?= Yii::t('label', 'Payment Method') ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td style="padding: 5px 10px 5px 0; border-top: 1px solid #ddd; text-align: left; vertical-align: top;"><?= Yii::$app->formatter->asDatetime($model->paid_at) ?></td>
				<td style="padding: 5px 10px 5px 10px; border-top: 1px solid #ddd; text-align: left; vertical-align: top;"><?= $model->getUnserializedValue('details')['transaction_id'] ?: '&mdash;' ?></td>
				<td style="padding: 5px 0 5px 10px; border-top: 1px solid #ddd; text-align: left; vertical-align: top;">
					<?= implode(' / ', array_filter([
						PaymentMetadata::getPaymentMethodLabels()[$model->payment_method],
						PaymentMetadata::getPaymentProcessorLabels()[$model->payment_processor],
					])) ?>
				</td>
			</tr>
		</tbody>
	</table>
<?php endif; ?>
