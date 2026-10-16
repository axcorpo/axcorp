<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Article */

use common\models\Article;
use backend\widgets\ActiveForm;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
		'class' => Yii::$app->request->isAjax ? 'modal-dialog modal-lg' : '',
	],
	'validateOnType' => true,
]); ?>
	<div class="form-body <?= Yii::$app->request->isAjax ? 'modal-content' : '' ?>">
		<?php if (Yii::$app->request->isAjax) : ?>
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
				<div class="modal-title"><?= $this->title ?></div>
			</div>
		<?php endif; ?>

		<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'status')->widget(Select2::class, [
						'data' => ArrayHelper::getColumn(Article::getStatusLabels(), 'label'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
			</div>
			<?= $form->field($model, 'article_category_id')->widget(Select2::class, [
				'options' => [
					'multiple' => true,
				],
				'data' => ArrayHelper::map(\common\models\ArticleCategory::findAllArticleCategories(), 'uuid', 'translation.title'),
				'maintainOrder' => true,
				'showToggleAll' => true,
				'toggleAllSettings' => [
					'selectLabel' => '<span class="glyphicon glyphicon-unchecked"></span> ' . Yii::t('common', 'Select All'),
					'unselectLabel' => '<span class="glyphicon glyphicon-check"></span> ' . Yii::t('common', 'Unselect All'),
				],
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => true,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
			<fieldset class="fieldset margin-bottom-10">
				<legend><?= Yii::t('label', 'Figure') ?></legend>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'imageFile')->widget(FileInput::class, [
							'options' => [
								'accept' => 'image/*',
								'data' => [
									'operation-confirm' => Yii::t('common', 'Are you sure you want to perform this operation?'),
								],
							],
							'resizeImages' => false,
							'sortThumbs' => false,
							'purifyHtml' => false,
							'pluginOptions' => [
								'allowedFileExtensions' => ['jpeg', 'jpg', 'png', 'gif'],
								'maxFileSize' => 2 * 1024 * 1024,
								'dropZoneEnabled' => false,
								'showClose' => false,
								'showUpload' => false,
								'showCaption' => true,
								'showRemove' => true,
								'showPreview' => true,
								'fileActionSettings' => [
									'showDownload' => true,
									'showRemove' => true,
									'showUpload' => false,
									'showZoom' => true,
									'showDrag' => false,
								],
								'initialPreview' => $model->getImageUrl() ?: false,
								'initialPreviewConfig' => [
									[
										'caption' => $model->image,
										'downloadUrl' => $model->getImageUrl(),
									],
								],
								'initialPreviewAsData' => true,
								'initialPreviewShowDelete' => true,
								'overwriteInitial' => true,
								'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
								'deleteExtraData' => [
									'attribute' => 'image',
								],
							],
						]) ?>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'video')->input('url', [
							'placeholder' => Yii::t('common', 'Example') . ': http://vieourl.tld'
						]) ?>
					</div>
					<div class="col-sm-6">
						<?= $form->field($model, 'icon')->widget(Select2::class, [
							'data' => \common\helpers\FontIcon::getDropdownIcons(),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => true,
								'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
				</div>
				<div class="text-info-icon fa-info-circle text-muted margin-top-10"><?= Yii::t('backend', 'If there is more than one figure selected, the video or the image it will be displayed first and the icon last.') ?></div>
			</fieldset>
			<?= $form->field($model, 'source')->textInput([
				'placeholder' => Yii::t('common', 'Example') . ': Wikipedia',
			])->hint(Yii::t('common', 'Write here the source of the article or leave it blank if the article content is original.')) ?>

            <div class="row">
                <div class="col-sm-3">
                    <?= $form->field($model, 'translator')->inline(true)->checkboxList([1 => Yii::t('label', 'Translator'), 2 => Yii::t('label', 'Overwrite')]) ?>
                </div>
            </div>

			<?php
			$i18nFields = [];
			foreach (\common\models\Language::findAllLanguages() as $language) {
				$i18nFields[] = [
					'label' => mb_strtoupper($language->language),
					'content' => $this->render('_i18n-fields', [
						'model' => $model,
						'form' => $form,
						'language' => $language,
					]),
					'active' => $language->language_id === Yii::$app->language,
				];
			}
			?>
			<?= Tabs::widget([
				'items' => $i18nFields,
			]) ?>
		</div>

		<?php if (Yii::$app->request->isAjax) : ?>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
				<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-success']) ?>
			</div>
		<?php else : ?>
			<div class="form-actions floating">
				<?= Html::submitButton('<span class="fa fa-check"></span>', [
					'class' => 'btn btn-xlg btn-fab btn-success',
					'title' => Yii::t('common', 'Save'),
					'data' => [
						'toggle' => 'tooltip',
					],
				]) ?>
			</div>
		<?php endif; ?>
	</div>
<?php ActiveForm::end(); ?>
