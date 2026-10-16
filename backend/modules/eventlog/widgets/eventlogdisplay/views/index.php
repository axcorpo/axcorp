<?php

/* @var $this yii\web\View */
/* @var $content string */

?>

<?php if ($this->context->showMetadata) : ?>
	<?= $this->render('_metadata') ?>
<?php endif; ?>

<?= $content ?>
