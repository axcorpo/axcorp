<?php

/* @var $this yii\web\View */
/* @var $model common\models\User */

use tws\helpers\Url;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Subscriber User')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Subscriber Users'),
		'url' => ['index'],
	],
	Yii::t('common', 'Update'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewUser'),
		'tag' => 'a',
		'url' => ['index'],
		'icon' => 'fa fa-list',
		'options' => [
			'class' => 'btn btn-sm btn-default',
			'title' => Yii::t('common', 'List'),
			'data' => [
				'toggle' => 'tooltip',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('createUser'),
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

$requestQueryParams = Yii::$app->request->getQueryParams();
$requestQueryParams['user_id'] = $model->id;
Yii::$app->request->setQueryParams($requestQueryParams);
?>

<?= $this->render('_form', [
	'model' => $model,
]) ?>

<div class="margin-top-5">
	<label class="control-label"><?= Yii::t('common', 'Specific Access') ?></label>
</div>
<?php if (Yii::$app->user->can('viewActivityDomain')): ?>
	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('common', 'Activity Domains') ?></div>
			<div class="panel-actions">
				<?php if (Yii::$app->user->can('deleteActivityDomain')): ?>
					<?= Html::button('<span class="fa fa-unlink"></span>', [
						'type' => 'button',
						'class' => 'btn btn-sm btn-danger hidden',
						'title' => Yii::t('common', 'Unlink'),
						'data' => [
							'toggle' => 'tooltip',
							'dt-bulk-operation' => 'unlink',
							'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
							'dt-url' => Url::to(['user-activity-domain/unlink', 'user_id' => $model->id]),
							'dt-table' => '#dt-user-activity-domains',
						],
					]) ?>
				<?php endif; ?>
				<?php if (Yii::$app->user->can('createActivityDomain')): ?>
					<?= Html::a('<span class="fa fa-link"></span>', ['user-activity-domain/link', 'user_id' => $model->id], [
						'type' => 'button',
						'class' => 'btn btn-sm btn-success',
						'title' => Yii::t('common', 'Link'),
						'data' => [
							'toggle' => 'tooltip',
							'popup-action' => '',
							'popup-done' => ['redrawDataTable' => '#dt-user-activity-domains'],
						],
					]) ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="panel-body">
			<?= $this->render('@user/views/user-activity-domain/index') ?>
		</div>
	</div>
<?php endif; ?>
