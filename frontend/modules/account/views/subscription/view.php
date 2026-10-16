<?php
/* @var $this yii\web\View */
/* @var $model common\models\Subscription */

use common\models\PackageFeature;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use common\models\Subscription;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Subscription')]);
Yii::$app->formatter->locale = Yii::$app->language;
?>

<?php if (Yii::$app->request->isAjax): ?>
<div class="modal-dialog modal-lg">
	<div class="modal-content">
		<div class="modal-header">
			<div class="modal-title"><?= $this->title ?></div>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		</div>
		<div class="modal-body p0">
<?php endif; ?>

			<div class="table-responsive">
				<?= DetailView::widget([
					'model' => $model,
					'options' => [
						'class' => 'table table-striped table-bordered detail-view detail-view-fixed',
					],
					'attributes' => [
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Code'),
							'value' => function (Subscription $model) {
								return $model->code ? Html::tag('code', Html::encode($model->code)) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => ($model->type == Subscription::TYPE_FEATURE && $model->parent) ? Yii::t('label', 'Subscription') : Yii::t('label', 'Package'),
							'value' => function (Subscription $model) {
								if ($model->type == Subscription::TYPE_FEATURE && $model->parent) {
									return $model->parent->formattedName ?: '&mdash;';
								}
								return $model->package->translation->name ?: '&mdash;';
							},
						],
						[
							'format' => 'raw',
							'label' => Yii::t('label', 'Features'),
							'value' => function (Subscription $model) {
								if ($model->subscriptionFeatures) {
									return $this->render('_features-list', [
										'model' => $model,
									]);
								}
								return Html::tag('div', '&mdash;', ['class' => 'p8']);
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Price'),
							'value' => function (Subscription $model) {
								return $model->price ? Yii::$app->formatter->asCurrency($model->price, $model->currency) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Billing Cycle'),
							'value' => function (Subscription $model) {
								return $model->getFormattedBillingCycle() ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Company'),
							'value' => function (Subscription $model) {
								return $model->company ? $model->company->name : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Next Due At'),
							'value' => function (Subscription $model) {
								return $model->end_at ? Yii::$app->formatter->asDatetime($model->end_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created At'),
							'value' => function (Subscription $model) {
								return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated At'),
							'value' => function (Subscription $model) {
								return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Status'),
							'value' => function (Subscription $model) {
								$status = Subscription::getStatusLabels()[$model->status];
								return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
							},
						],
					],
				]) ?>
			</div>

<?php if (Yii::$app->request->isAjax): ?>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Close') ?></button>
		</div>
	</div>
</div>
<?php endif; ?>
