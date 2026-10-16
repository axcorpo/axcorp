<?php
/* @var $this yii\web\View */
/* @var $form common\widgets\ActiveForm */
/* @var $model common\models\Company */

/*
 * The company fields, shared by this form and the public affiliate application page
 * (frontend/views/site/affiliate.php), so a company is entered the same way everywhere.
 */

use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use common\models\Country;
use kartik\select2\Select2;
use tws\helpers\Url;
use tws\widgets\typeahead\Typeahead;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;

?>

<div class="row">
	<div class="col-sm-4">
		<?= $form->field($model, 'tin')->textInput([
			'data' => [
				'autofill-target' => '#' . $form->id,
				'autofill-url' => Url::to(['/commercial/company/find']),
				'autofill-param' => 'tin',
				'autofill-data' => Json::encode([
					'data.registrationNumber' => '#' . Html::getInputId($model, 'registration_number'),
					'data.name' => '#' . Html::getInputId($model, 'name'),
					'data.email' => '#' . Html::getInputId($model, 'email'),
					'data.phone' => '#' . Html::getInputId($model, 'phone'),
					'data.address.locality' => '#' . Html::getInputId($model, 'locality'),
					'data.address.county' => '#' . Html::getInputId($model, 'county'),
					'data.address.country' => '#' . Html::getInputId($model, 'country'),
					'data.address.zipCode' => '#' . Html::getInputId($model, 'zip_code'),
					'data.partialAddress' => '#' . Html::getInputId($model, 'address'),
				]),
			],
		]) ?>
	</div>
	<div class="col-sm-4">
		<?= $form->field($model, 'registration_number')->textInput() ?>
	</div>
	<div class="col-sm-4">
		<?= $form->field($model, 'name')->textInput() ?>
	</div>
</div>
<div class="row">
	<div class="col-sm-6">
		<?= $form->field($model, 'email')->input('email') ?>
	</div>
	<div class="col-sm-6">
		<?= $form->field($model, 'phone')->widget(PhoneInput::class, [
			'jsOptions' => PhoneInputConfig::jsOptions(),
			'options' => PhoneInputConfig::inputClassOptions(),
		]) ?>
	</div>
</div>
<div class="row">
	<div class="col-sm-4">
		<?= $form->field($model, 'country')->widget(Select2::class, [
			'data' => ArrayHelper::map(Country::find()
				->alias('c')
				->joinWith(['countryTranslations ct'])
				->andWhere([
					'c.status' => Country::STATUS_ACTIVE,
					'c.deleted' => Country::NO,
				])
				->orderBy(['c.name' => SORT_ASC])
				->indexBy('iso_alpha2')
				->all(), 'iso_alpha2', 'translation.name'),
			'pluginLoading' => false,
			'pluginOptions' => [
				'allowClear' => false,
				'placeholder' => Yii::t('common', 'Choose'),
			],
		]) ?>
	</div>
	<div class="col-sm-4">
		<?= $form->field($model, 'county')->widget(Typeahead::class, [
			'options' => [
				'class' => 'form-control',
				'type' => 'text',
				'placeholder' => '',
				'autocomplete' => 'off-county',
			],
			'name' => 'county',
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
									"country_code": $("#' . Html::getInputId($model, 'country') . '").val() 
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
		]) ?>
	</div>
	<div class="col-sm-4">
		<?= $form->field($model, 'locality')->widget(Typeahead::class, [
			'options' => [
				'class' => 'form-control',
				'type' => 'text',
				'placeholder' => '',
				'autocomplete' => 'off-locality',
			],
			'name' => 'locality',
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
									"county": $("#' . Html::getInputId($model, 'county') . '").val()
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
		])->hint(Yii::t('backend', 'Locality shall take one of the values “Sector 1”, “Sector2”,  “Sector  3”,  “Sector 4”,  “Sector  5”  or “Sector 6” if county is “Bucharest”.')) ?>
	</div>
</div>
<div class="row">
	<div class="col-sm-4">
		<?= $form->field($model, 'zip_code')->textInput() ?>
	</div>
	<div class="col-sm-8">
		<?= $form->field($model, 'address')->textInput() ?>
	</div>
</div>

<?php
$this->registerJs('
	$(document).ready(function() {
	    var countrySelector = "#' . Html::getInputId($model, 'country') . '";
	    var countySelector = "#' . Html::getInputId($model, 'county') . '";
	    function toggleRequiredClass() {
	        if ($(countrySelector).val() == "RO") {
	            $(countySelector).closest(".form-group").addClass("required");
	        } else {
	            $(countySelector).closest(".form-group").removeClass("required");
	        }
	    }
	    toggleRequiredClass();
	    $(countrySelector).on("change", function() {
	        setTimeout(toggleRequiredClass, 100);
	    });
	});
');
?>
