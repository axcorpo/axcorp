<?php
/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;

\frontend\assets\AppAsset::register($this);
\common\widgets\gallery\GalleryAsset::register($this);

$bodyAttributes = array_merge_recursive($this->context->bodyAttributes, (array) $this->params['bodyAttributes']);
?>
<?php $this->beginPage() ?>
<?= $this->render('_begin', ['bodyAttributes' => $bodyAttributes]) ?>

<?php $this->params['breadcrumbs'] ?: $this->params['breadcrumbs'][] = Html::encode($this->title); ?>
<?php if ($this->context->currentPage->module != 'product' || ($this->context->currentPage->module == 'product' && Yii::$app->request->get('category'))): ?>
	<?php
	$title = $this->title;
	if (isset($this->context->module) && in_array($this->context->module->id, ['article', 'service']) && $this->context->action->id === 'view') {
		$page = \common\models\Page::findPageByRoute(["/{$this->context->module->id}/{$this->context->id}/index"]);
		if ($page) {
			$title = $page->translation->title;
		}
	}
	?>
	<?= $this->render('_page-title', ['title' => $title]) ?>
<?php endif; ?>

<!-- Main Content -->
<div class="main-content">
	<?= $content ?>
</div>
<!-- ./Main Content -->

<?= $this->render('_end') ?>
<?php $this->endPage() ?>
