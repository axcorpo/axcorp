<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use backend\widgets\Alert;
use tws\helpers\Url;

\backend\assets\AuthenticationAsset::register($this);
\kartik\growl\GrowlAsset::register($this);

$bodyAttributes = array_merge_recursive((array) $this->params['bodyAttributes'], [
	'class' => 'page-authentication',
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
	<div class="center-item animated fadeInDown">
		<div class="authentication-card">
			<div class="card-header text-center">
				<?= Html::a(
					Html::img(Url::to('@uploads/' . Yii::$app->settings->get('appLogo')) ?: Url::to('@web/img/logo.png'), [
						'alt' => Yii::$app->name,
						'class' => 'img-responsive',
					]),
					'/' . mb_substr(Yii::$app->language, 0, 2),
					['class' => 'logo']
				) ?>
			</div>
			<div class="card-body">
				<h1 class="card-title"><?= Html::encode($this->title) ?></h1>
				<?= Alert::widget() ?>
				<?= $content ?>
			</div>
			<div class="card-footer">&copy; <?= Yii::$app->name ?> <?= date('Y') ?></div>
		</div>
	</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
