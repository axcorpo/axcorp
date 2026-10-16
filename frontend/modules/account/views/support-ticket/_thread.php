<?php
/**
 * The ticket conversation as chat bubbles: the replies (newest first), then the opening
 * message. Shared by the view modal and the reply modal, so the two always match.
 * Ported from the masteranunturi workspace app.
 *
 * @var yii\web\View $this
 * @var common\models\SupportTicket $model
 */

use common\helpers\HtmlSanitizer;
use yii\helpers\Html;
use tws\helpers\Url;

// Splits an attachment list (getAttachments(true, true)) into images and plain files.
$splitAttachments = function (array $attachments) {
	$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	$images = $files = [];
	foreach ($attachments as $attachment) {
		$extension = strtolower((string) pathinfo($attachment['name'], PATHINFO_EXTENSION));
		if (in_array($extension, $imageExtensions, true)) {
			$images[] = $attachment;
		} else {
			$files[] = $attachment;
		}
	}
	return [$images, $files];
};

// Renders one attachments block (thumbnails for images, download links for the rest).
$renderAttachments = function (array $attachments, $nested = false) use ($splitAttachments) {
	[$images, $files] = $splitAttachments($attachments);
	if ($images === [] && $files === []) {
		return '';
	}
	ob_start();
	?>
	<div class="support-ticket-view__opener-attachments<?= $nested ? ' support-ticket-view__opener-attachments--nested' : '' ?>">
		<p class="support-ticket-view__opener-attachments-title"><?= Html::encode(Yii::t('label', 'Attachments')) ?></p>
		<?php if ($images !== []): ?>
			<ul class="support-ticket-view__opener-attachment-thumbs list-unstyled" role="list">
				<?php foreach ($images as $attachment): ?>
					<li>
						<a href="<?= Html::encode($attachment['url']) ?>" target="_blank" rel="noopener noreferrer" class="support-ticket-view__opener-attachment-thumb-link">
							<img src="<?= Html::encode($attachment['url']) ?>" alt="<?= Html::encode($attachment['name']) ?>" class="support-ticket-view__opener-attachment-thumb" loading="lazy"/>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ($files !== []): ?>
			<ul class="support-ticket-view__opener-attachment-files list-unstyled">
				<?php foreach ($files as $attachment): ?>
					<li>
						<span class="fa fa-paperclip support-ticket-view__opener-attachment-file-icon" aria-hidden="true"></span>
						<?= Html::a(Html::encode($attachment['name']), $attachment['url'], [
							'class' => 'support-ticket-view__opener-attachment-file-link',
							'target' => '_blank',
							'rel' => 'noopener noreferrer',
							'download' => $attachment['name'],
						]) ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
};
?>

<div class="support-ticket-view__thread">
	<?php /** @var common\models\SupportTicketComment $supportTicketComment */ ?>
	<?php foreach ($model->getSupportTicketComments()->recent()->active()->deleted(false)->all() as $supportTicketComment): ?>
		<?php $isOwner = $supportTicketComment->uuidOf('created_by') == Yii::$app->user->id; ?>
		<div class="support-ticket-view__message <?= $isOwner ? 'support-ticket-view__message--user' : 'support-ticket-view__message--staff' ?>">
			<div class="support-ticket-view__message-head">
				<div class="support-ticket-view__avatar-wrap">
					<img class="support-ticket-view__avatar" src="<?= Html::encode($supportTicketComment->creator->getImageUrl() ?: Url::to('@web/img/img-placeholder-user.png')) ?>" alt="" width="40" height="40"/>
				</div>
				<div class="support-ticket-view__message-meta">
					<div class="support-ticket-view__author">
						<?= Html::encode($supportTicketComment->creator->fullName) ?>
						<?php if (!$isOwner): ?>
							<span class="support-ticket-view__label support-ticket-view__label--staff"><?= Html::encode(Yii::t('common', 'Staff')) ?></span>
						<?php endif; ?>
					</div>
					<div class="support-ticket-view__time"><?= Yii::$app->formatter->asDatetime($supportTicketComment->created_at) ?></div>
				</div>
			</div>
			<div class="support-ticket-view__message-body support-ticket-view__message-body--richtext">
				<?= HtmlSanitizer::richText($supportTicketComment->content) ?>
			</div>
			<?php if ($attachmentsHtml = $renderAttachments($supportTicketComment->getAttachments(true, true), true)): ?>
				<div class="support-ticket-view__comment-attachments"><?= $attachmentsHtml ?></div>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<div class="support-ticket-view__message support-ticket-view__message--user support-ticket-view__message--opener">
		<div class="support-ticket-view__message-head">
			<div class="support-ticket-view__avatar-wrap">
				<img class="support-ticket-view__avatar" src="<?= Html::encode($model->creator->getImageUrl() ?: Url::to('@web/img/img-placeholder-user.png')) ?>" alt="" width="40" height="40"/>
			</div>
			<div class="support-ticket-view__message-meta">
				<div class="support-ticket-view__author">
					<?= Html::encode($model->creator->fullName) ?>
				</div>
				<div class="support-ticket-view__time"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
			</div>
		</div>
		<div class="support-ticket-view__message-body support-ticket-view__message-body--richtext">
			<?= HtmlSanitizer::richText($model->content) ?>
		</div>
		<?= $renderAttachments($model->getAttachments(true, true)) ?>
	</div>
</div>
