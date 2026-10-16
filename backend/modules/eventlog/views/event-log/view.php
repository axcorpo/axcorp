<?php

/* @var $this yii\web\View */
/* @var $model \yii\db\ActiveRecord */
/* @var $eventLog common\models\EventLog */
/* @var $viewPath string */

use backend\modules\eventlog\widgets\eventlogdisplay\EventLogDisplay;
use yii\helpers\Html;

$this->title = Yii::t('common', 'Event Log') . ' ::: ' . Yii::$app->formatter->asDatetime($eventLog->created_at);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Event Logs'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [];

$initialData = $eventLog->getInitialData();
$finalData = $eventLog->getFinalData();
$colSize = empty($initialData) || empty($finalData) ? 12 : 6;
?>

<?php EventLogDisplay::begin([
	'id' => 'event-log-display',
	'title' => $this->title,
	'modal' => Yii::$app->request->isAjax,
	'model' => $eventLog,
	'showMetadata' => true,
	'clientOptions' => [
		'targets' => '.detail-view',
		'highlight' => true,
		'highlightClass' => 'warning',
	],
]); ?>
	<div class="row">
		<?php if (!empty($initialData)) : ?>
			<div class="col-md-<?= $colSize ?>">
				<div class="panel panel-table panel-info">
					<div class="panel-heading">
						<h3 class="panel-title bold"><?= Yii::t('label', 'Initial Data') ?></h3>
					</div>
					<?= $this->renderFile("{$viewPath}/view.php", [
						'model' => $initialData,
						'showModal' => false,
						'showEventLogs' => false,
					]) ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if (!empty($finalData)) : ?>
			<div class="col-md-<?= $colSize ?>">
				<div class="panel panel-table panel-info">
					<div class="panel-heading">
						<h3 class="panel-title bold"><?= Yii::t('label', 'Final Data') ?></h3>
					</div>
					<?= $this->renderFile("{$viewPath}/view.php", [
						'model' => $finalData,
						'showModal' => false,
						'showEventLogs' => false,
					]) ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
<?php EventLogDisplay::end(); ?>

<?php
// Set the title and params again since they are overwritten by the previous view rendering
$this->title = Yii::t('common', 'Event Log') . ' ::: ' . Yii::$app->formatter->asDatetime($eventLog->created_at);
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Event Logs'),
		'url' => ['index'],
	],
	$this->title,
];
$this->params['actions'] = [];
?>