<?php
/* @var $this yii\web\View */

use yii\helpers\Html;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="section section-md pt-0">
	<div class="container-fluid">
		<?php if ($content = $this->context->currentPage->content): ?>
			<header class="section-header">
				<?= $content ?>
			</header>
		<?php endif; ?>

		<?= $this->render('/support-ticket/index') ?>
	</div>
</div>
