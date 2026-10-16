<?php
/* @var $this yii\web\View */

use yii\helpers\Html;
?>

<section class="section content-page">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-10 col-xl-9">
				<div class="rich-content">
					<?= $this->context->currentPage->content ?>
				</div>
			</div>
		</div>
	</div>
</section>
