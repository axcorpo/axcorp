<?php
/* @var $this yii\web\View */
/* @var $model common\models\User */

use yii\helpers\Html;
use tws\helpers\Url;

$this->params['breadcrumbs'][] = Html::encode($this->title);
$currentPage = $this->context->currentPage;
?>

<?php if (Yii::$app->request->isAjax): ?>
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<div class="modal-title"><?= $currentPage->translation->title ?></div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body"><?= $currentPage->content ?></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light btn-slide-right" data-bs-dismiss="modal"><?= Yii::t('common', 'Close') ?></button>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="section section-md">
		<div class="container-fluid">
			<?= $currentPage->content ?>
		</div>
	</div>
<?php endif; ?>
