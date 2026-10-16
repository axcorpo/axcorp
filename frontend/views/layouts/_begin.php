<?php
/* @var $this \yii\web\View */
/* @var $bodyAttributes array */

use tws\helpers\Url;
use yii\helpers\Html;

// The whole site runs on the template's "AI Agency Dark" variant (body-style-dark), styled further by css/theme-dark.css.
Html::addCssClass($bodyAttributes, 'body-style-dark');
?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
	<meta charset="<?= Yii::$app->charset ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php $this->registerCsrfMetaTags() ?>
	<title><?= implode(' - ', array_filter([Html::encode($this->title), Html::encode(Yii::$app->name)])) ?></title>
	<?php $this->head() ?>
</head>
<body <?= Html::renderTagAttributes($bodyAttributes) ?>>
<?php $this->beginBody() ?>

<?= $this->render('@app/views/layouts/_widgets') ?>

	<main id="wrapper">
		<!-- Preload -->
		<div class="preload preload-container">
			<div class="spinner-circle lg">
				<?php for ($i = 1; $i <= 9; $i++): ?>
					<span class="spinner-circle<?= $i ?> spinner-child"></span>
				<?php endfor; ?>
			</div>
		</div>
		<!-- /Preload -->

<?= $this->render('@app/views/layouts/_header') ?>
