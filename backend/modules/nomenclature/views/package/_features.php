<?php
/* @var $this yii\web\View */
/* @var $model common\models\Package */

use common\widgets\datatable\DataTable;
use yii\helpers\Html;
use yii\web\JsExpression;
use tws\helpers\Url;
?>

<div class="panel box blue-hoki">
	<div class="panel-title">
		<div class="panel-heading"><?= Yii::t('common', 'Features') ?></div>
		<div class="panel-actions">
			<?php if (Yii::$app->user->can('updatePackage')): ?>
				<?= Html::a('<span class="fa fa-plus"></span>', ['create-package-feature', 'package_id' => $model->id], [
					'type' => 'button',
					'class' => 'btn btn-sm btn-success',
					'title' => Yii::t('common', 'Create'),
					'data' => [
						'toggle' => 'tooltip',
						'popup-action' => '',
						'popup-done' => ['redrawDataTable' => '#dt-package-features'],
					],
				]) ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="panel-body">
		<?= DataTable::widget([
			'id' => 'dt-package-features',
			'options' => [
				'class' => 'table table-bordered table-hover',
			],
			'showColumnFilters' => false,
			'clientOptions' => [
				'deferRender' => true,
				'processing' => true,
				'serverSide' => true,
				'ajax' => [
					'url' => Url::to(['dt-package-features']),
					'method' => 'POST',
					'data' => new JsExpression('function (data) {
						data.package_id = ' . json_encode($model->uuid) . ';
					}'),
				],
				'order' => [
					[1, 'asc'],
				],
				'pageLength' => Yii::$app->settings->get('itemsPerPage'),
				'lengthMenu' => [
					'autoCreate' => true,
					'displayAll' => Yii::t('common', 'All'),
				],
				'autoWidth' => false,
				'responsive' => true,
				'columns' => [
					[
						'class' => 'common\widgets\datatable\ActionColumn',
						'data' => 'action',
						'title' => Yii::t('common', 'Action'),
					],
					[
						'data' => 'name',
						'title' => Yii::t('label', 'Name'),
					],
					[
						'data' => 'value',
						'title' => Yii::t('label', 'Value'),
					],
				],
			],
		]) ?>
	</div>
</div>
