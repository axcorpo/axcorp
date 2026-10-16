<?php

/* @var $this \yii\web\View */
/* @var $models \common\models\Notification[] */
/* @var $modelsCount int */

use common\helpers\DateHelper;
use yii\helpers\Html;
use tws\helpers\Url;

$modelsCount = count($models);
?>

<a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
	<?= Html::tag('i', null, [
		'class' => 'fa ' . ($modelsCount ? 'fa-bell infinite-tada-animation' : 'fa-bell-o'),
	]) ?>
	<?php if ($modelsCount > 0) : ?>
		<span class="badge badge-default"><?= $modelsCount ?></span>
	<?php endif; ?>
</a>
<ul class="dropdown-menu">
	<?php if ($modelsCount > 0): ?>
		<li class="external">
			<h3 class="bold">
				<?php if ($modelsCount === 1): ?>
					<?= $modelsCount . ' ' . mb_strtolower(Yii::t('common', 'Notification')) ?>
				<?php else: ?>
					<?= $modelsCount . ' ' . mb_strtolower(Yii::t('common', 'Notifications')) ?>
				<?php endif; ?>
			</h3>
			<div class="dropdown-actions">
				<?= Html::a('<span class="fa fa-eye"></span>', ['/notification-manager/notification/mark-as-seen'], [
					'class' => 'btn btn-xs btn-info',
					'title' => Yii::t('common', 'Mark All as Seen'),
					'data' => [
						'toggle' => 'tooltip',
						'action' => 'mark-as-seen',
					],
				]) ?>
			</div>
		</li>
		<li>
			<ul class="dropdown-menu-list scroller" style="max-height: 380px;" data-handle-color="#637283">
				<?php foreach ($models as $model) : ?>
					<li>
						<a class="notification-item" href="<?= Url::to(['/notification-manager/notification/view', 'id' => $model->id]) ?>">
							<?= Html::tag('span', Html::tag('i', null, ['class' => $model->icon ?: 'fa fa-bell-o']), [
								'class' => 'label label-sm label-icon label-info',
								'style' => $model->color ? "background-color: {$model->color};" : null,
							]) ?>
							<span class="title"><?= $model->title ?: Yii::t('common', 'Info') ?></span>
							<span class="time"><?= Yii::$app->formatter->asRelativeTime($model->created_at) ?></span>
							<?php if ($model->message) : ?>
								<span class="excerpt"><?= $model->getMessageExcerpt(50) ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</li>
		<li class="dropdown-menu-footer text-center">
			<?= Html::a(Yii::t('common', 'View List'), ['/notification-manager/notification/index'], [
				'class' => 'btn',
			]) ?>
		</li>
	<?php else: ?>
		<li class="external"><?= Yii::t('common', 'No records found.') ?></li>
	<?php endif; ?>
</ul>
