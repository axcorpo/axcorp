<?php

/* @var $this yii\web\View */
/* @var $content string */

?>

<div class="modal-content">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
		<div class="modal-title"><?= $this->context->title ?></div>
	</div>
	<div class="modal-body">
		<?php if ($this->context->showMetadata) : ?>
			<?= $this->render('_metadata') ?>
		<?php endif; ?>

		<?= $content ?>
	</div>
</div>
