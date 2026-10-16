<?php
/* @var $this \yii\web\View */
/* @var $content string */

\frontend\assets\AppAsset::register($this);
\common\widgets\gallery\GalleryAsset::register($this);

$bodyAttributes = array_merge_recursive($this->context->bodyAttributes, (array) $this->params['bodyAttributes']);
$bodyAttributes = array_merge_recursive($bodyAttributes, ['class' => 'home']);
?>
<?php $this->beginPage() ?>
<?= $this->render('_begin', ['bodyAttributes' => $bodyAttributes]) ?>

<!-- Main Content -->
<div class="main-content">
	<?= $content ?>
</div>
<!-- ./Main Content -->

<?= $this->render('_end') ?>
<?php $this->endPage() ?>
