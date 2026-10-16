<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\marketing\models\DirectMarketingCampaign */
/* @var $searchModel common\models\MarketingRecipientSearchForm */

use yii\helpers\Html;

$this->title = Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Email Campaign')]);
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
	Yii::t('common', 'Create'),
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
];
?>

<?= $this->render('_form', [
	'model' => $model,
	'searchModel' => $searchModel,
]) ?>
