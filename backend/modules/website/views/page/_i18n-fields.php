<?php

/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Page */
/* @var $language common\models\Language */

use common\models\Page;
use yii\helpers\Html;
use tws\widgets\tinymce\TinyMCE;
use kartik\select2\Select2;
?>

<?php
	$titleOptions = [
		'data' => [
			'seo-meta' => 'title',
			'seo-minlength' => 15,
			'seo-maxlength' => 70,
			'seo-minlength-hint' => Yii::t('backend', 'The entered text has %s characters. It is recommended to have at least %s characters.'),
			'seo-maxlength-hint' => Yii::t('backend', 'The entered text has %s characters. Maximum number of characters should not exceed %s.'),
		],
	];
	if ($model->isNewRecord) {
			$titleOptions['data']['copy-value'] = [
			'hyphenate' => '#' . Html::getInputId($model, "slug[{$language->language_id}]"),
		];
	}
?>
<?= $form->field($model, "title[{$language->language_id}]", [
		'options' => [
			'class' => 'form-group' . ($language->language_id == Yii::$app->language ? ' required' : ''),
		],
	])
	->textInput($titleOptions)
	->hint(Yii::t('backend', 'Recommended length is under {n} characters.', ['n' => 70])) ?>

<?php if ($model->default == Page::NO): ?>
	<?= $form->field($model, "slug[{$language->language_id}]")
		->textInput()
		->hint(Yii::t('backend', 'The page URL segment. Leave it blank to be generated from the page title.')) ?>
<?php endif; ?>

<?= $form->field($model, "keywords[{$language->language_id}]")->widget(Select2::class, [
		'options' => [
			'multiple' => true,
			'data' => [
				'seo-meta' => 'keywords',
				'seo-minlength' => 5,
				'seo-maxlength' => 10,
				'seo-minlength-hint' => Yii::t('backend', '%s keywords were introduced. It is recommended to have at least %s keywords.'),
				'seo-maxlength-hint' => Yii::t('backend', '%s keywords were introduced. Maximum number of keywords should not exceed %s.'),
			],
		],
		'pluginLoading' => true,
		'showToggleAll' => false,
		'pluginOptions' => [
			'allowClear' => false,
			'tags' => true,
			'tokenSeparators' => [',', ';'],
			'maximumSelectionLength' => 10,
		],
	])
	->hint(Yii::t('backend', '{min} to {max} words that best describe the page.', ['min' => 5, 'max' => 10])) ?>

<?= $form->field($model, "description[{$language->language_id}]")
	->textarea([
		'rows' => 3,
		'data' => [
			'insert-target-scope' => 'false',
			'seo-meta' => 'description',
			'seo-minlength' => 120,
			'seo-maxlength' => 150,
			'seo-minlength-hint' => Yii::t('backend', 'The entered text has %s characters. It is recommended to have at least %s characters.'),
			'seo-maxlength-hint' => Yii::t('backend', 'The entered text has %s characters. Maximum number of characters should not exceed %s.'),
			'seo-keyscount-hint' => Yii::t('backend', '%s keywords were used %s. It is recommended to use at least 2 keywords in description.'),
		],
	])
	->hint(Yii::t('backend', 'Recommended length is between {min}-{max} characters.', ['min' => 120, 'max' => 150])) ?>

<?= $form->field($model, "content[{$language->language_id}]")->widget(TinyMCE::class, [
	'clientOptions' => [
		// TODO 1: check if the frontend main css file should be used here to display editor content with custom style?
//		'content_css' => [
//			str_replace(Yii::$app->request->getBaseUrl(), '', Yii::getAlias('@web/css/main-app.css')),
//		],
		// TODO 2: create some templates with grid or move it to the widget itsef?
//		'templates' => [
//			['title' => 'Test template 1', 'content' => 'Test 1'],
//			['title' => 'Test template 2', 'content' => 'Test 2'],
//		],
	],
])->hint(Yii::t('backend', 'Move the cursor at the desired position, then click a short code to insert it into the current template.')) ?>


<?= $this->render('@backend/views/_shared/_shortcodes', [
	'shortCodes' => \common\models\Carousel::getShortCodeItems(),
	'insertTarget' => [
		'#' . Html::getInputId($model, "content[{$language->language_id}]"),
	],
]) ?>

<?= $this->render('@backend/views/_shared/_shortcodes', [
	'shortCodes' => \common\models\Page::getShortCodeItems(),
	'insertTarget' => [
		'#' . Html::getInputId($model, "description[{$language->language_id}]"),
		'#' . Html::getInputId($model, "content[{$language->language_id}]"),
	],
]) ?>
