<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Invoice */

use common\models\Country;
use common\models\Invoice;
use backend\widgets\ActiveForm;
use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use kartik\select2\Select2;
use tws\helpers\Url;
use tws\widgets\datetimepicker\DateTimePicker;
use tws\widgets\typeahead\Typeahead;
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
			<div class="panel blue-hoki">
				<div class="panel-title">
					<div class="panel-heading"><?= Yii::t('backend', 'Merchant') ?></div>
				</div>
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][tin]')->textInput([
								'data' => [
									'autofill-target' => '#' . mb_strtolower($model->formName()),
									'autofill-url' => Url::to(['/commercial/company/find']),
									'autofill-param' => 'tin',
									'autofill-data' => Json::encode([
										'data.registrationNumber' => '#' . Html::getInputId($model, 'details[merchant][registration_number]'),
										'data.name' => '#' . Html::getInputId($model, 'details[merchant][name]'),
										'data.email' => '#' . Html::getInputId($model, 'details[merchant][email]'),
										'data.phone' => '#' . Html::getInputId($model, 'details[merchant][phone]'),
										'data.address.locality' => '#' . Html::getInputId($model, 'details[merchant][locality]'),
										'data.address.county' => '#' . Html::getInputId($model, 'details[merchant][county]'),
										'data.address.country' => '#' . Html::getInputId($model, 'details[merchant][country]'),
										'data.address.zipCode' => '#' . Html::getInputId($model, 'details[merchant][zip_code]'),
										'data.partialAddress' => '#' . Html::getInputId($model, 'details[merchant][address]'),
									]),
								],
							])->label(Yii::t('label', 'Tin')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][registration_number]')->textInput()->label(Yii::t('label', 'Registration Number')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][name]')->textInput()->label(Yii::t('label', 'Name')) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][country]')->widget(Select2::class, [
								'data' => ArrayHelper::map(Country::findAllCountries(), 'iso_alpha2', function ($model) {
									return $model->translation->name ?: $model->name;
								}),
								'pluginLoading' => false,
								'pluginOptions' => [
									'allowClear' => true,
									'placeholder' => Yii::t('common', 'Choose'),
								],
							])->label(Yii::t('label', 'Country')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][county]')->widget(Typeahead::class, [
								'options' => [
									'class' => 'form-control',
									'type' => 'text',
									'placeholder' => '',
									'autocomplete' => 'off-county',
								],
								'name' => 'details[merchant][county]',
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
									'backdrop' => [
										'background-color' => '#ffffff',
										'opacity' => '0.4',
									],
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
														"country_code": $("#' . Html::getInputId($model, 'details[merchant][country]') . '").val() 
													}
												};
											}'),
										],
									],
									'callback' => [
										'onClick' => new JsExpression('function (node, a, item, event) {
											if (item.url) {
												window.location.href = item.url;
											}
										}'),
									],
								],
							])->label(Yii::t('label', 'County')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][locality]')->widget(Typeahead::class, [
								'options' => [
									'class' => 'form-control',
									'type' => 'text',
									'placeholder' => '',
									'autocomplete' => 'off-locality',
								],
								'name' => 'details[merchant][locality]',
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
									'backdrop' => [
										'background-color' => '#ffffff',
										'opacity' => '0.4',
									],
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
														"county": $("#' . Html::getInputId($model, 'details[merchant][county]') . '").val()
													}
												};
											}'),
										],
									],
									'callback' => [
										'onClick' => new JsExpression('function (node, a, item, event) {
											if (item.url) {
												window.location.href = item.url;
											}
										}'),
									],
								],
							])->label(Yii::t('label', 'Locality'))->hint(Yii::t('backend', 'Locality shall take one of the values “Sector 1”, “Sector2”,  “Sector  3”,  “Sector 4”,  “Sector  5”  or “Sector 6” if county is “Bucharest”.')) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][address]')->textInput()->label(Yii::t('label', 'Address')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][email]')->textInput()->label(Yii::t('label', 'Email')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[merchant][phone]')->widget(PhoneInput::class, [
									'jsOptions' => PhoneInputConfig::jsOptions(),
									'options' => PhoneInputConfig::inputClassOptions(),
								])->label(Yii::t('label', 'Phone')) ?>
						</div>
					</div>
					<?php if (!empty($model->details['merchant'])): ?>
						<?php foreach ($model->details['merchant'] as $key => $value): ?>
							<?php if (!in_array($key, ['name', 'tin', 'registration_number', 'email', 'phone', 'address', 'country', 'county', 'locality'])): ?>
								<?= $form->field($model, "details[merchant][$key]")->hiddenInput()->label(false) ?>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="panel blue-hoki">
				<div class="panel-title">
					<div class="panel-heading"><?= Yii::t('backend', 'Client') ?></div>
				</div>
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][tin]')->textInput([
								'data' => [
									'autofill-target' => '#' . mb_strtolower($model->formName()),
									'autofill-url' => Url::to(['/commercial/company/find']),
									'autofill-param' => 'tin',
									'autofill-data' => Json::encode([
										'data.registrationNumber' => '#' . Html::getInputId($model, 'details[client][registration_number]'),
										'data.name' => '#' . Html::getInputId($model, 'details[client][name]'),
										'data.email' => '#' . Html::getInputId($model, 'details[client][email]'),
										'data.phone' => '#' . Html::getInputId($model, 'details[client][phone]'),
										'data.address.locality' => '#' . Html::getInputId($model, 'details[client][locality]'),
										'data.address.county' => '#' . Html::getInputId($model, 'details[client][county]'),
										'data.address.country' => '#' . Html::getInputId($model, 'details[client][country]'),
										'data.address.zipCode' => '#' . Html::getInputId($model, 'details[client][zip_code]'),
										'data.partialAddress' => '#' . Html::getInputId($model, 'details[client][address]'),
									]),
								],
							])->label(Yii::t('label', 'Tin')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][registration_number]')->textInput()->label(Yii::t('label', 'Registration Number')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][name]')->textInput()->label(Yii::t('label', 'Company')) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][country]')->widget(Select2::class, [
								'data' => ArrayHelper::map(Country::findAllCountries(), 'iso_alpha2', function ($model) {
									return $model->translation->name ?: $model->name;
								}),
								'pluginLoading' => false,
								'pluginOptions' => [
									'allowClear' => true,
									'placeholder' => Yii::t('common', 'Choose'),
								],
							])->label(Yii::t('label', 'Country')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][county]')->widget(Typeahead::class, [
								'options' => [
									'class' => 'form-control',
									'type' => 'text',
									'placeholder' => '',
									'autocomplete' => 'off-county',
								],
								'name' => 'details[client][county]',
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
									'backdrop' => [
										'background-color' => '#ffffff',
										'opacity' => '0.4',
									],
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
														"country_code": $("#' . Html::getInputId($model, 'details[client][country]') . '").val() 
													}
												};
											}'),
										],
									],
									'callback' => [
										'onClick' => new JsExpression('function (node, a, item, event) {
											if (item.url) {
												window.location.href = item.url;
											}
										}'),
									],
								],
							])->label(Yii::t('label', 'County')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][locality]')->widget(Typeahead::class, [
								'options' => [
									'class' => 'form-control',
									'type' => 'text',
									'placeholder' => '',
									'autocomplete' => 'off-locality',
								],
								'name' => 'details[client][locality]',
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
									'backdrop' => [
										'background-color' => '#ffffff',
										'opacity' => '0.4',
									],
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
														"county": $("#' . Html::getInputId($model, 'details[client][county]') . '").val()
													}
												};
											}'),
										],
									],
									'callback' => [
										'onClick' => new JsExpression('function (node, a, item, event) {
											if (item.url) {
												window.location.href = item.url;
											}
										}'),
									],
								],
							])->label(Yii::t('label', 'Locality'))->hint(Yii::t('backend', 'Locality shall take one of the values “Sector 1”, “Sector2”,  “Sector  3”,  “Sector 4”,  “Sector  5”  or “Sector 6” if county is “Bucharest”.')) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][address]')->textInput()->label(Yii::t('label', 'Address')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][email]')->textInput()->label(Yii::t('label', 'Email')) ?>
						</div>
						<div class="col-sm-4">
							<?= $form->field($model, 'details[client][phone]')->widget(PhoneInput::class, [
									'jsOptions' => PhoneInputConfig::jsOptions(),
									'options' => PhoneInputConfig::inputClassOptions(),
								])->label(Yii::t('label', 'Phone')) ?>
						</div>
					</div>
					<?php if (!empty($model->details['client'])): ?>
						<?php foreach ($model->details['client'] as $key => $value): ?>
							<?php if (!in_array($key, ['name', 'tin', 'registration_number', 'email', 'phone', 'address', 'country', 'county', 'locality'])): ?>
								<?= $form->field($model, "details[client][$key]")->hiddenInput()->label(false) ?>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="panel blue-hoki">
				<div class="panel-title">
					<div class="panel-heading"><?= Yii::t('backend', 'e-Invoice') ?></div>
				</div>
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-3">
							<?= $form->field($model, 'e_invoice_status')->widget(Select2::class, [
								'data' => array_map(function ($status) {
									return Html::tag('span', $status['label'], ['class' => 'label label-' . $status['color']]);
								}, Invoice::getEStatusLabels()),
								'pluginLoading' => false,
								'pluginOptions' => [
									'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
									'placeholder' => Yii::t('common', 'Choose'),
								],
							]) ?>
						</div>
						<div class="col-sm-3">
							<?= $form->field($model, 'e_invoice_upload_id')->textInput() ?>
						</div>
						<div class="col-sm-3">
							<?= $form->field($model, 'e_invoice_download_id')->textInput() ?>
						</div>
						<div class="col-sm-3">
							<?= $form->field($model, 'e_invoice_sent_at')->widget(DateTimePicker::class, [
								'options' => [
									'value' => Yii::$app->formatter->asDate($model->e_invoice_sent_at),
								],
								'clientOptions' => [
									'format' => 'icu:' . Yii::$app->settings->get('dateFormat'),
									'ignoreReadonly' => true,
									'showTodayButton' => true,
									'showClear' => true,
									'showClose' => true,
									'allowInputToggle' => true,
									'useCurrent' => false,
								],
							]) ?>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-12">
							<?= $form->field($model, 'e_invoice_error')->textInput() ?>
						</div>
					</div>
				</div>
			</div>
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

<?php
$this->registerJs('
	$(document).ready(function() {
	    var merchantCountrySelector = "#' . Html::getInputId($model, 'details[merchant][country]') . '";
	    var merchantCountySelector = "#' . Html::getInputId($model, 'details[merchant][county]') . '";
	    function toggleMerchantRequiredClass() {
	        if ($(merchantCountrySelector).val() == "RO") {
	            $(merchantCountySelector).closest(".form-group").addClass("required");
	        } else {
	            $(merchantCountySelector).closest(".form-group").removeClass("required");
	        }
	    }
	    toggleMerchantRequiredClass();
	    $(merchantCountrySelector).on("change", function() {
	        setTimeout(toggleMerchantRequiredClass, 100);
	    });
	    
	    var clientCountrySelector = "#' . Html::getInputId($model, 'details[client][country]') . '";
	    var clientCountySelector = "#' . Html::getInputId($model, 'details[client][county]') . '";
	    function toggleClientRequiredClass() {
	        if ($(clientCountrySelector).val() == "RO") {
	            $(clientCountySelector).closest(".form-group").addClass("required");
	        } else {
	            $(clientCountySelector).closest(".form-group").removeClass("required");
	        }
	    }
	    toggleClientRequiredClass();
	    $(clientCountrySelector).on("change", function() {
	        setTimeout(toggleClientRequiredClass, 100);
	    });
	});
');
?>
