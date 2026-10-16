<?php

/* @var $this \yii\web\View */
/* @var $content string */

use backend\widgets\Alert;
use common\widgets\Actions;
use kartik\dialog\Dialog;
use yii\helpers\Html;
use yii\widgets\Breadcrumbs;

\backend\assets\AppAsset::register($this);
\kartik\growl\GrowlAsset::register($this);
\common\widgets\gallery\GalleryAsset::register($this);

$bodyAttributes = array_merge_recursive($this->context->bodyAttributes, (array) $this->params['bodyAttributes']);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
	<meta charset="<?= Yii::$app->charset ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php $this->registerCsrfMetaTags() ?>
	<title><?= Html::encode($this->title) . ' ::: ' . Html::encode(Yii::$app->name) ?></title>
	<?php $this->head() ?>
</head>
<body <?= Html::renderTagAttributes($bodyAttributes) ?>>
<?php $this->beginBody() ?>
	<div class="page-wrapper">
		<?= $this->render('_header') ?>
		<div class="clearfix"></div>
		<div class="page-container">
			<?= $this->render('_sidebar') ?>
			<div class="page-content-wrapper">
				<div class="page-content">
					<div class="page-bar">
						<?= Breadcrumbs::widget([
							'options' => [
								'class' => 'page-breadcrumb',
							],
							'encodeLabels' => false,
							'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
						]) ?>
						<?= Actions::widget([
							'options' => [
								'class' => 'page-toolbar',
							],
							'items' => $this->params['actions'],
						]) ?>
					</div>
					<?= Alert::widget() ?>

					<?= $content ?>

					<?= Dialog::widget([
						'id' => 'application-dialog',
						'libName' => 'appDialog',
						'useNative' => false,
						'showDraggable' => true,
						'overrideYiiConfirm' => true,
						'jsPosition' => \yii\web\View::POS_END,
						'dialogDefaults' => [
							Dialog::DIALOG_ALERT => [
								'closable' => false,
								'draggable' => true,
								'type' => Dialog::TYPE_INFO,
								'title' => Yii::t('common', 'Info'),
								'buttonLabel' => '<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('common', 'Ok')
							],
							Dialog::DIALOG_CONFIRM => [
								'closable' => false,
								'draggable' => false,
								'type' => Dialog::TYPE_WARNING,
								'title' => Yii::t('common', 'Confirmation'),
								'btnOKClass' => 'btn-warning',
								'btnOKLabel' => '<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('common', 'Yes'),
								'btnCancelLabel' => '<span class="glyphicon glyphicon-ban-circle"></span> ' . Yii::t('common', 'No'),
							],
							Dialog::DIALOG_PROMPT => [
								'closable' => false,
								'draggable' => false,
								'title' => Yii::t('common', 'Info'),
								'buttons' => [
									[
										'icon' => 'glyphicon glyphicon-ban-circle',
										'label' => Yii::t('common', 'Cancel'),
									],
									[
										'icon' => 'glyphicon glyphicon-ok',
										'label' => Yii::t('common', 'Ok'),
										'cssClass' => 'btn-primary',
									],
								],
							],
							Dialog::DIALOG_OTHER => [
								'closable' => true,
								'draggable' => false,
								'type' => Dialog::TYPE_SUCCESS,
								'title' => Yii::t('common', 'Info'),
								'buttons' => [
									[
										'icon' => 'glyphicon glyphicon-ban-circle',
										'label' => Yii::t('common', 'Cancel'),
									],
									[
										'icon' => 'glyphicon glyphicon-ok',
										'label' => Yii::t('common', 'Ok'),
										'cssClass' => 'btn-success',
									],
								],
							],
						],
					]) ?>
				</div>
			</div>
		</div>
		<?= $this->render('_footer') ?>
	</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
