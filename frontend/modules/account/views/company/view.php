<?php
/* @var $this yii\web\View */
/* @var $model common\models\Company */

use common\models\Company;
use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = Yii::t('common', 'View {item}', ['item' => Yii::t('common', 'Company')]);
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
							'label' => Yii::t('label', 'Name'),
							'value' => function (Company $model) {
								return $model->name ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Registration Number'),
							'value' => function (Company $model) {
								return $model->registration_number ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Tin'),
							'value' => function (Company $model) {
								return $model->tin ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Email'),
							'value' => function (Company $model) {
								return $model->email ? Html::a($model->email, "mailto:{$model->email}") : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Phone'),
							'value' => function (Company $model) {
								return $model->phone ? Html::a($model->phone, "tel:{$model->phone}") : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Address'),
							'value' => function (Company $model) {
								return $model->getFullAddress() ?: '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Created At'),
							'value' => function (Company $model) {
								return $model->created_at ? Yii::$app->formatter->asDatetime($model->created_at) : '&mdash;';
							},
						],
						[
							'format' => 'html',
							'label' => Yii::t('label', 'Updated At'),
							'value' => function (Company $model) {
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
