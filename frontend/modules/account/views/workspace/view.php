<?php
/* @var $this yii\web\View */
/* @var $model common\models\Workspace */

use common\models\Workspace;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Workspace')]);
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
							'value' => function (Workspace $model) {
								return $model->code ? Html::tag('code', Html::encode($model->code)) : '&mdash;';
							},
						],
						[
							'format' => 'raw',
							'label' => Yii::t('label', 'URL'),
							'value' => function (Workspace $model) {
								if ($model->url) {
									return Html::a($model->getAbsoluteUrl(), $model->getAbsoluteUrl(), ['target' => '_blank']);
								}
								return '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Subscription'),
							'value' => function (Workspace $model) {
								if ($subscription = $model->subscription) {
									return Html::a($subscription->formattedName, ['/account/subscription/index']);
								}
								return '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Activity Domain'),
							'value' => function (Workspace $model) {
								return $model->activityDomain ? $model->activityDomain->translation->name : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created At'),
							'value' => function (Workspace $model) {
								return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated At'),
							'value' => function (Workspace $model) {
								return $model->updated_at ? Yii::$app->formatter->asDatetime($model->updated_at) : '&mdash;';
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
