<?php

/* @var $this yii\web\View */
/* @var $model common\models\WorkspaceDatabaseUpdateForm */
/* @var $form backend\widgets\ActiveForm */
/* @var $results common\models\WorkspaceDatabaseUpdateLog[] */

use common\models\WorkspaceDatabaseUpdateLog;
use common\widgets\datatable\DataTable;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use backend\widgets\ActiveForm;
use tws\helpers\Url;
use yii\web\View;

$results = isset($results) ? $results : [];

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Workspace')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscribers'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Workspaces'),
		'url' => ['workspace/index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewWorkspace'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'Workspaces'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createWorkspace'),
		'tag' => 'a',
		'url' => ['create'],
		'icon' => 'fa fa-plus',
		'options' => [
			'class' => 'btn btn-sm btn-success',
			'title' => Yii::t('common', 'Create'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
];
?>

<?php if (!empty($model->workspaceCode)): ?>
	<div class="alert alert-info">
		<?= Yii::t('common', 'The query will run only against the workspace {code}.', [
			'code' => Html::tag('code', Html::encode($model->workspaceCode)),
		]) ?>
		<?= Html::a(Yii::t('common', 'Run on all workspaces instead'), ['database-update'], ['class' => 'alert-link pull-right']) ?>
	</div>
<?php endif; ?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
<div class="row">
	<div class="col-sm-12">
		<?= $form->field($model, "query")->textarea(['rows' => 25]) ?>
	</div>
</div>
<div class="form-actions floating">
	<?= Html::submitButton('<span class="fa fa-check"></span>', [
		'class' => 'btn btn-xlg btn-fab btn-success',
		'title' => Yii::t('common', 'Save'),
		'data' => [
			'toggle' => 'tooltip',
		]
	]) ?>
</div>
<?php ActiveForm::end(); ?>

<?php if (!empty($results)): ?>
	<div class="row">
		<div class="col-sm-12">
			<table class="table table-bordered table-hover">
				<thead>
					<tr>
						<th><?= Yii::t('label', 'Code') ?></th>
						<th><?= Yii::t('label', 'Url') ?></th>
						<th><?= Yii::t('label', 'Status') ?></th>
						<th><?= Yii::t('label', 'Error') ?></th>
						<th class="text-right"><?= Yii::t('common', 'Action') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($results as $log): ?>
						<?php $status = WorkspaceDatabaseUpdateLog::getResultLabels()[$log->status]; ?>
						<tr class="<?= $log->getIsSuccessful() ? 'success' : 'danger' ?>">
							<td><?= Html::tag('code', Html::encode($log->workspace_code)) ?></td>
							<td><?= $log->workspace_url ? Html::encode($log->workspace_url) : '&mdash;' ?></td>
							<td><?= Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]) ?></td>
							<td><?= $log->error ? Html::encode($log->error) : '&mdash;' ?></td>
							<td class="text-right">
								<?php if (!$log->getIsSuccessful()): ?>
									<?= Html::a('<span class="fa fa-repeat"></span> ' . Yii::t('common', 'Rerun'), ['database-update', 'code' => $log->workspace_code], [
										'class' => 'btn btn-xs btn-warning',
									]) ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<div class="row">
	<div class="col-sm-12">
		<h3><?= Yii::t('common', 'Workspace Database Update History') ?></h3>
	</div>
</div>

<?= DataTable::widget([
	'id' => 'dt-database-update-logs',
	'options' => [
		'class' => 'table table-bordered table-hover',
	],
	'showColumnFilters' => true,
	'clientOptions' => [
		'deferRender' => true,
		'processing' => true,
		'serverSide' => true,
		'ajax' => [
			'url' => Url::to(['dt-database-update-logs']),
			'method' => 'POST',
		],
		'order' => [
			[6, 'desc'],
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
				'orderable' => 'false',
			],
			[
				'data' => 'workspace_code',
				'title' => Yii::t('label', 'Code'),
				'filter' => ['text'],
				'className' => 'col-autowidth',
			],
			[
				'data' => 'workspace_url',
				'title' => Yii::t('label', 'Url'),
				'filter' => ['text'],
			],
			[
				'data' => 'status',
				'title' => Yii::t('label', 'Status'),
				'className' => 'col-autowidth',
				'filter' => ['select', ArrayHelper::getColumn(WorkspaceDatabaseUpdateLog::getResultLabels(), 'label')],
			],
			[
				'data' => 'query',
				'title' => Yii::t('label', 'Query'),
				'filter' => ['text'],
				'orderable' => 'false',
			],
			[
				'data' => 'error',
				'title' => Yii::t('label', 'Error'),
				'filter' => ['text'],
				'orderable' => 'false',
			],
			[
				'data' => 'created_at',
				'title' => Yii::t('label', 'Created At'),
				'filter' => ['date', 'icu:' . Yii::$app->settings->get('dateFormat')],
			],
		],
	],
]) ?>

<?php
$copySuccessMessage = Json::encode(Yii::t('common', 'The query was copied to the clipboard.'));
$copyErrorMessage = Json::encode(Yii::t('common', 'The query could not be copied.'));
$copyButtonTitle = Json::encode(Yii::t('common', 'Copy'));
$copiedButtonTitle = Json::encode(Yii::t('common', 'Copied'));

$this->registerJs(<<<JS
(function () {
	var tableSelector = '#dt-database-update-logs';
	var copySuccessMessage = {$copySuccessMessage};
	var copyErrorMessage = {$copyErrorMessage};
	var copyButtonTitle = {$copyButtonTitle};
	var copiedButtonTitle = {$copiedButtonTitle};

	var copyText = function (text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
			return Promise.resolve();
		} catch (error) {
			return Promise.reject(error);
		} finally {
			document.body.removeChild(textarea);
		}
	};

	var showCopySuccess = function (\$btn) {
		if (window.notify && typeof window.notify.success === 'function') {
			window.notify.success(copySuccessMessage);
		}

		var \$icon = \$btn.find('span.fa');
		var originalClass = \$icon.attr('class');
		\$icon.attr('class', 'fa fa-check');
		\$btn.tooltip('hide')
			.attr('data-original-title', copiedButtonTitle)
			.tooltip('fixTitle')
			.tooltip('show');

		window.setTimeout(function () {
			\$icon.attr('class', originalClass);
			\$btn.tooltip('hide')
				.attr('data-original-title', copyButtonTitle)
				.tooltip('fixTitle');
		}, 2000);
	};

	jQuery(document).on('click', tableSelector + ' .action-copy-query', function (e) {
		e.preventDefault();

		var text = this.getAttribute('data-clipboard-text');
		if (!text) {
			return;
		}

		var \$btn = jQuery(this);
		copyText(text).then(function () {
			showCopySuccess(\$btn);
		}).catch(function () {
			if (window.notify && typeof window.notify.error === 'function') {
				window.notify.error(copyErrorMessage);
			}
		});
	});
})();
JS
, View::POS_READY);
?>
