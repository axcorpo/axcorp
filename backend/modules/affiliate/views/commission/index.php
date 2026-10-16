<?php

/* @var $this yii\web\View */
/* @var $affiliate common\models\Affiliate|null the affiliate the list is scoped to */

use backend\modules\affiliate\models\CommissionSearch;
use common\services\AffiliateStatsService;
use tws\helpers\Url;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Commissions');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Affiliates'),
		'url' => ['affiliate/index'],
	],
	$this->title,
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('manageAffiliatePayout'),
		'tag' => 'button',
		'icon' => 'fa fa-check',
		'options' => [
			'class' => 'btn btn-sm btn-success hidden',
			'title' => Yii::t('common', 'Approve (make payable now)'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'approve',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['approve']),
				'dt-table' => '#dt-commissions',
			],
		],
	],
	[
		'visible' => Yii::$app->user->can('manageAffiliatePayout'),
		'tag' => 'button',
		'icon' => 'fa fa-ban',
		'options' => [
			'class' => 'btn btn-sm btn-danger hidden',
			'title' => Yii::t('common', 'Cancel'),
			'data' => [
				'toggle' => 'tooltip',
				'dt-bulk-operation' => 'cancel',
				'dt-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
				'dt-url' => Url::to(['cancel']),
				'dt-table' => '#dt-commissions',
			],
		],
	],
	[
		'visible' => true,
		'content' => $this->render('/_export', [
			'dataTable' => '#dt-commissions',
			'model' => CommissionSearch::class,
			'title' => $this->title,
		]),
	],
];
?>

<?php if ($affiliate): ?>
	<?php // Opened from an affiliate's page: the list is that affiliate's (server-side context, not a column filter). ?>
	<p>
		<?= Yii::t('label', 'Affiliate') ?>:
		<?= Html::a(Html::encode(AffiliateStatsService::affiliateLabel($affiliate)), ['affiliate/view', 'id' => $affiliate->uuid]) ?>
		&middot;
		<?= Html::a(Yii::t('common', 'All'), ['index']) ?>
	</p>
<?php endif; ?>

<?= $this->render('_grid', [
	'id' => 'dt-commissions',
	'params' => $affiliate ? ['affiliate_id' => $affiliate->uuid] : [],
	'selectable' => Yii::$app->user->can('manageAffiliatePayout'),
]) ?>
