<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Subscriber */

use backend\widgets\ActiveForm;
use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use common\models\User;
use tws\widgets\datetimepicker\DateTimePicker;
use tws\widgets\typeahead\Typeahead;
use kartik\file\FileInput;
use kartik\select2\Select2;
use tws\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
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
		<?php if (Yii::$app->request->isAjax): ?>
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
				<div class="modal-title"><?= $this->title ?></div>
			</div>
		<?php endif; ?>

		<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
			<?php if ($model->hasErrors() && empty(array_intersect_key($model->getErrors(), array_flip($model->safeAttributes())))): ?>
				<?= $form->errorSummary($model, [
					'header' => false,
					'class' => 'alert alert-danger alert-icon',
				]) ?>
			<?php endif; ?>
			<div class="row">
				<?php if (!$model->isNewRecord || Yii::$app->settings->get('userAccountActivation') == User::ACCOUNT_ACTIVATION_AUTOMATIC): ?>
					<div class="col-lg-4">
						<?= $form->field($model, 'status')->widget(Select2::class, [
							'data' => ArrayHelper::getColumn(User::getStatusLabels(), 'label'),
							'pluginLoading' => false,
							'pluginOptions' => [
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="row">
				<div class="col-lg-4">
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
							'maxFileSize' => Yii::$app->settings->get('maxFileSize'),
							'dropZoneEnabled' => false,
							'showClose' => false,
							'showUpload' => false,
							'showCaption' => true,
							'showRemove' => false,
							'showPreview' => true,
							'fileActionSettings' => [
								'showDownload' => true,
								'showRemove' => true,
								'showUpload' => false,
								'showZoom' => true,
								'showDrag' => false,
							],
							'initialPreview' => $model->user->imageUrl ?: false,
							'initialPreviewConfig' => [
								[
									'caption' => $model->user->image,
									'downloadUrl' => $model->user->imageUrl,
								],
							],
							'initialPreviewAsData' => true,
							'initialPreviewShowDelete' => true,
							'overwriteInitial' => true,
							'deleteUrl' => Url::to(['/user-manager/user/delete-file', 'id' => $model->user_id]),
							'deleteExtraData' => [
								'attribute' => 'image',
							],
						],
					]) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-md-4">
					<?= $form->field($model, 'email')->input('email', [
						'data' => [
							'autofill-target' => '#' . mb_strtolower($model->formName()),
							'autofill-url' => Url::to(['/subscriber-manager/subscriber/find-existing-users']),
							'autofill-method' => 'POST',
							'autofill-clear-invalid' => 'false',
							'autofill-clear-empty' => 'false',
							'autofill-data' => Json::encode([
								'data.phone' => '#' . Html::getInputId($model, 'phone'),
								'data.gender' => '#' . Html::getInputId($model, 'gender'),
								'data.first_name' => '#' . Html::getInputId($model, 'first_name'),
								'data.middle_name' => '#' . Html::getInputId($model, 'middle_name'),
								'data.last_name' => '#' . Html::getInputId($model, 'last_name'),
							]),
						],
					]) ?>
				</div>
				<div class="col-md-4">
					<?= $form->field($model, 'phone')->widget(PhoneInput::class, [
						'jsOptions' => PhoneInputConfig::jsOptions(),
						'options' => array_merge(PhoneInputConfig::inputClassOptions(), [
							'data' => [
								'autofill-target' => '#' . mb_strtolower($model->formName()),
								'autofill-url' => Url::to(['/subscriber-manager/subscriber/find-existing-users']),
								'autofill-method' => 'POST',
								'autofill-clear-invalid' => 'false',
								'autofill-clear-empty' => 'false',
								'autofill-data' => Json::encode([
									'data.email' => '#' . Html::getInputId($model, 'email'),
									'data.gender' => '#' . Html::getInputId($model, 'gender'),
									'data.first_name' => '#' . Html::getInputId($model, 'first_name'),
									'data.middle_name' => '#' . Html::getInputId($model, 'middle_name'),
									'data.last_name' => '#' . Html::getInputId($model, 'last_name'),
								]),
							],
						]),
					]) ?>
				</div>
				<div class="col-md-4">
					<?= $form->field($model, 'gender')->inline()->radioList(User::getGenderLabels()) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-4">
					<?= $form->field($model, 'last_name')->textInput() ?>
				</div>
				<div class="col-sm-4">
					<?= $form->field($model, 'first_name')->textInput() ?>
				</div>
				<div class="col-sm-4">
					<?= $form->field($model, 'middle_name')->textInput() ?>
				</div>
			</div>
			<div class="row">
				<div class="col-md-4">
					<?= $form->field($model, 'date_of_birth')->widget(DateTimePicker::class, [
						'options' => [
							'value' => $model->date_of_birth ? Yii::$app->formatter->asDate($model->date_of_birth) : null,
							'placeholder' => Yii::$app->settings->get('dateFormat'),
						],
						'clientOptions' => [
							'format' => 'icu:' . Yii::$app->settings->get('dateFormat'),
							'maxDate' => (new DateTime)->format(DATE_ATOM),
							'ignoreReadonly' => true,
							'showTodayButton' => false,
							'showClear' => true,
							'showClose' => true,
							'allowInputToggle' => true,
							'useCurrent' => false,
						],
					]) ?>
				</div>
				<div class="col-md-4">
					<?= $form->field($model, 'pin')->textInput() ?>
				</div>
			</div>
			<fieldset class="fieldset margin-bottom-10">
				<legend><?= Yii::t('label', 'Address') ?></legend>
				<div class="row">
					<div class="col-md-4">
						<?= $form->field($model, 'country')->widget(Select2::class, [
							'data' => ArrayHelper::map(\common\models\Country::findAllCountries(), 'iso_alpha2', 'translation.name'),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => true,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
					<div class="col-md-4">
						<?php // Same suggestion-only search the account forms use: /site/search
							// returns nothing but `label`, so picking one can only fill the field.
							// County narrows by the chosen country, locality by the typed county. ?>
						<?= $form->field($model, 'county')->widget(Typeahead::class, [
							'options' => [
								'class' => 'form-control',
								'type' => 'text',
								'placeholder' => '',
								'autocomplete' => 'off-county',
							],
							'clientOptions' => [
								'minLength' => 2,
								'maxItem' => 10,
								'hint' => true,
								'accent' => [
									'from' => 'âăîşţșț',
									'to' => 'aaistst',
								],
								'cancelButton' => false,
								'dynamic' => true,
								'searchOnFocus' => true,
								'source' => [
									'results' => [
										'display' => 'label',
										'ajax' => new JsExpression('function (query) {
											return {
												"method": "POST",
												"url": "' . Url::to(['/site/search']) . '",
												"path": "results",
												"data": {
													"county": query,
													"country_code": jQuery("#' . Html::getInputId($model, 'country') . '").val()
												}
											};
										}'),
									],
								],
							],
						]) ?>
					</div>
					<div class="col-md-4">
						<?= $form->field($model, 'locality')->widget(Typeahead::class, [
							'options' => [
								'class' => 'form-control',
								'type' => 'text',
								'placeholder' => '',
								'autocomplete' => 'off-locality',
							],
							'clientOptions' => [
								'minLength' => 2,
								'maxItem' => 10,
								'hint' => true,
								'accent' => [
									'from' => 'âăîşţșț',
									'to' => 'aaistst',
								],
								'cancelButton' => false,
								'dynamic' => true,
								'searchOnFocus' => true,
								'source' => [
									'results' => [
										'display' => 'label',
										'ajax' => new JsExpression('function (query) {
											return {
												"method": "POST",
												"url": "' . Url::to(['/site/search']) . '",
												"path": "results",
												"data": {
													"locality": query,
													"county": jQuery("#' . Html::getInputId($model, 'county') . '").val()
												}
											};
										}'),
									],
								],
							],
						]) ?>
					</div>
				</div>
				<div class="row">
					<div class="col-md-4">
						<?= $form->field($model, 'zip_code')->textInput() ?>
					</div>
					<div class="col-md-8">
						<?= $form->field($model, 'address')->textInput() ?>
					</div>
				</div>
			</fieldset>
		</div>

		<?php if (Yii::$app->request->isAjax): ?>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
				<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-success']) ?>
			</div>
		<?php else: ?>
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
