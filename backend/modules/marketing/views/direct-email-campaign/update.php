<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\marketing\models\DirectMarketingCampaign */
/* @var $searchModel common\models\MarketingRecipientSearchForm */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Update {item}', ['item' => Yii::t('common', 'Email Campaign')]);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Marketing'),
		'url' => ['default/index'],
	],
	[
		'label' => Yii::t('common', 'Direct'),
		'url' => ['index'],
	],
	[
		'label' => Yii::t('common', 'Email Campaigns'),
		'url' => ['index'],
	],
	Yii::t('common', 'Update'),
];
$this->params['actions'] = [
	[
		'visible' => Yii::$app->user->can('viewDirectMarketingCampaign'),
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
		'visible' => Yii::$app->user->can('createDirectMarketingCampaign'),
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

<?= $this->render('_form', [
	'model' => $model,
	'searchModel' => $searchModel,
]) ?>
