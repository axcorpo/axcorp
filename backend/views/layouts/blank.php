<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use backend\widgets\Alert;
use tws\helpers\Url;

\backend\assets\BlankAsset::register($this);
\kartik\growl\GrowlAsset::register($this);

$bodyAttributes = array_merge_recursive((array) $this->params['bodyAttributes'], [
	'class' => 'page-blank',
]);
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
	<div class="page-container">
		<header class="page-header">
			<div class="container-fluid">
				<?= Html::a(
					Html::img(Url::to('@uploads/' . Yii::$app->settings->get('appLogo')) ?: Url::to('@web/img/logo.png'), [
						'alt' => Yii::$app->name,
						'class' => 'img-responsive',
					]),
					'/' . mb_substr(Yii::$app->language, 0, 2),
					['class' => 'logo']
				) ?>
			</div>
		</header>

		<main class="page-body">
			<div class="container-fluid">
				<h1 class="page-title"><?= Html::encode($this->title) ?></h1>
				<?= Alert::widget() ?>
				<?= $content ?>
			</div>
		</main>

		<footer class="page-footer">
			<div class="container-fluid">
				&copy; <?= Yii::$app->name ?> <?= date('Y') ?>
			</div>
		</footer>
	</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
