<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\SupportTicket */
/* @var $commentModel common\models\SupportTicketComment */

use backend\widgets\ActiveForm;
use tws\widgets\tinymce\TinyMCE;
use yii\helpers\Html;
use tws\helpers\Url;

?>

<div class="form-group">
	<div class="control-label"><?= Yii::t('common', 'Messages') ?></div>
</div>

<div class="panel panel-default">
	<div class="panel-heading cursor-pointer" data-toggle="collapse" data-target="#<?= mb_strtolower($commentModel->formName()) ?>">
		<span class="fa fa-pencil"></span>
		<?= Yii::t('common', 'Reply') ?>
	</div>
	<?php $form = ActiveForm::begin([
		'id' => mb_strtolower($commentModel->formName()),
		'options' => [
			'novalidate' => true,
			'class' => 'collapse',
		],
		'validateOnType' => true,
	]); ?>
	<div class="panel-body">
		<?= $form->field($commentModel, 'content')->widget(TinyMCE::class, [
			'clientOptions' => [
				'mode' => 'none',
				'menubar' => false,
				'forced_root_block' => '',
				'plugins' => 'paste autoresize link autolink lists wordcount contextmenu codesample',
				'toolbar1' => 'undo redo | bold italic | link | alignleft aligncenter alignright alignjustify | numlist bullist | codesample | removeformat',
				'paste_as_text' => true,
				'autoresize_on_init' => true,
				'autoresize_min_height' => 100,
				'autoresize_bottom_margin' => 5,
			],
		]) ?>
	</div>
	<div class="panel-footer">
		<?= Html::submitButton(Yii::t('common', 'Create {item}', ['item' => Yii::t('common', 'Message')]), ['class' => 'btn btn-block btn-success']) ?>
	</div>
	<?php ActiveForm::end(); ?>
</div>

<div class="scroll-container-sm">
	<?php /** @var \common\models\SupportTicketComment $supportTicketComment */ ?>
	<?php foreach ($model->getSupportTicketComments()->recent()->active()->deleted(false)->all() as $supportTicketComment): ?>
		<?php $isOwner = $supportTicketComment->created_by == Yii::$app->user->id; ?>
		<div class="panel <?= $isOwner ? 'panel-primary' : 'panel-success'?>">
			<div class="panel-heading">
				<div class="media">
					<div class="media-left">
						<img class="media-object media-object-sm rounded bg-white" src="<?= $supportTicketComment->creator->imageUrl ?: Url::to("@web/img/img-placeholder-user.png") ?>" alt="<?= $supportTicketComment->creator->fullName ?>"/>
					</div>
					<div class="media-body">
						<h3 class="panel-title">
							<?= $supportTicketComment->creator->fullName ?> <?= $isOwner ? '' : ('(' . Yii::t('common', 'Client') . ')') ?>
							<div class="font-xxs"><?= Yii::$app->formatter->asDatetime($supportTicketComment->created_at) ?></div>
						</h3>
					</div>
				</div>
			</div>
			<div class="panel-body"><?= $supportTicketComment->content ?></div>
		</div>
	<?php endforeach; ?>

	<?php $isOwner = $model->created_by == Yii::$app->user->id; ?>
	<div class="panel <?= $isOwner ? 'panel-primary' : 'panel-success'?>">
		<div class="panel-heading">
			<div class="media">
				<div class="media-left">
					<img class="media-object media-object-sm rounded bg-white" src="<?= $model->creator->imageUrl ?: Url::to("@web/img/img-placeholder-user.png") ?>" alt="<?= $model->creator->fullName ?>"/>
				</div>
				<div class="media-body">
					<h3 class="panel-title">
						<?= $model->creator->fullName ?> <?= $isOwner ? '' : ('(' . Yii::t('common', 'Client') . ')') ?>
						<div class="font-xxs"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
					</h3>
				</div>
			</div>
		</div>
		<div class="panel-body"><?= \common\helpers\HtmlSanitizer::richText($model->content) ?></div><?php /* Purified on the way out as well as on the way in: the rule was added later than some of the stored content. */ ?>
	</div>
</div>
