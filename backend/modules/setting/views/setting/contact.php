<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\ContactSettingForm */
/* @var $form backend\widgets\ActiveForm */

use common\models\Country;
use backend\widgets\ActiveForm;
use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use kartik\select2\Select2;
use tws\helpers\Url;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;

$this->title = Yii::t('backend', 'Contact Settings');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	$this->title,
];
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
<div class="panel blue-hoki">
	<div class="panel-title">
		<div class="panel-heading"><?= Yii::t('common', 'General') ?></div>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'mobilePhone')->widget(PhoneInput::class, [
					'jsOptions' => PhoneInputConfig::jsOptions(),
					'options' => PhoneInputConfig::inputClassOptions(),
				]) ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'fixedPhone')->input('tel') ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'fax')->input('tel') ?>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'email')->input('email') ?>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'lastName')->textInput() ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'firstName')->textInput() ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'middleName')->textInput() ?>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'tin')->textInput([
					'data' => [
						'autofill-target' => '#' . mb_strtolower($model->formName()),
						'autofill-url' => Url::to(['/commercial/company/find']),
						'autofill-param' => 'tin',
						'autofill-data' => Json::encode([
							'data.registrationNumber' => '#' . Html::getInputId($model, 'registrationNumber'),
							'data.name' => '#' . Html::getInputId($model, 'company'),
						]),
					],
				]) ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'registrationNumber')->textInput() ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'company')->textInput() ?>
			</div>
		</div>
		<?php
		$i18nFields = [];
		foreach (\common\models\Language::findAllLanguages() as $language) {
			$i18nFields[] = [
				'label' => mb_strtoupper($language->language),
				'content' => $form->field($model, "schedule[{$language->language_id}]")->textarea(['rows' => 3]),
				'active' => $language->language_id === Yii::$app->language,
			];
		}
		?>
		<?= Tabs::widget([
			'items' => $i18nFields,
		]) ?>
	</div>
</div>

<div class="panel blue-hoki">
	<div class="panel-title">
		<div class="panel-heading"><?= Yii::t('label', 'Address') ?></div>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-sm-6">
				<div class="text-info-icon fa-info-circle text-muted margin-bottom-10"><?= Yii::t('backend', 'Drag the map marker to the desired position.') ?></div>
				<div id="map-container" style="width: 100%; height: 400px;"></div>
			</div>
			<div class="col-sm-6">
                <div class="row">
                    <div class="col-sm-12">
						<?= $form->field($model, 'address')->textInput() ?>
                    </div>
                </div>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'latitude')->textInput() ?>
					</div>
					<div class="col-sm-6">
						<?= $form->field($model, 'longitude')->textInput() ?>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'streetName')->textInput() ?>
					</div>
					<div class="col-sm-6">
						<?= $form->field($model, 'streetNumber')->textInput() ?>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'locality')->textInput() ?>
					</div>
					<div class="col-sm-6">
						<?= $form->field($model, 'zipCode')->textInput() ?>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-6">
						<?= $form->field($model, 'county')->textInput() ?>
					</div>
					<div class="col-sm-6">
						<?= $form->field($model, 'country')->widget(Select2::class, [
							'data' => ArrayHelper::map(Country::findAllCountries(), 'iso_alpha2', 'translation.name'),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => true,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
					</div>
				</div>
				<?= Html::checkbox('', true, [
					'id' => 'update-address-checkbox',
					'value' => 1,
					'label' => Yii::t('backend', 'Update address while dragging the map marker'),
					'labelOptions' => [
						'class' => 'mt-checkbox mt-checkbox-outline',
					],
				]) ?>
			</div>
		</div>
	</div>
</div>

<div class="form-actions floating">
	<?= Html::submitButton('<span class="fa fa-check"></span>', [
		'class' => 'btn btn-xlg btn-fab btn-success',
		'title' => Yii::t('common', 'Save'),
		'data' => [
			'toggle' => 'tooltip',
		],
	]) ?>
</div>
<?php ActiveForm::end(); ?>

<script type="text/javascript">
	(function (window, document, undefined) {
        var defaultLat = "<?= $model->latitude ?: 45.7519379 ?>",
            defaultLng = "<?= $model->longitude ?: 21.2259002 ?>",
            latitudeControl = document.getElementById("<?= Html::getInputId($model, 'latitude') ?>"),
            longitudeControl = document.getElementById("<?= Html::getInputId($model, 'longitude') ?>"),
            addressControl = document.getElementById("<?= Html::getInputId($model, 'address') ?>"),
            localityControl = document.getElementById("<?= Html::getInputId($model, 'locality') ?>"),
            countyControl = document.getElementById("<?= Html::getInputId($model, 'county') ?>"),
            countryControl = document.getElementById("<?= Html::getInputId($model, 'country') ?>"),
            zipCodeControl = document.getElementById("<?= Html::getInputId($model, 'zipCode') ?>"),
            streetNameControl = document.getElementById("<?= Html::getInputId($model, 'streetName') ?>"),
            streetNumberControl = document.getElementById("<?= Html::getInputId($model, 'streetNumber') ?>"),
            updateAddressCheckbox = document.getElementById("update-address-checkbox");

        var geocode = function (address) {
            var geocoder = new google.maps.Geocoder();

            geocoder.geocode({address: address}, function (results, status) {
                if (status === google.maps.GeocoderStatus.OK) {
                    var addressComponents = [],
                        geometry = [],
                        latitude = '',
                        longitude = '',
                        locality = '',
                        county = '',
                        country = '',
                        zipCode = '',
                        streetName = '',
                        streetNumber = '';

                    for (var i = 0, len = results.length; i < len; i++) {
                        if (results[i].address_components) {
                            addressComponents = results[i].address_components;
                            break;
                        }
                    }
                    for (var i = 0, len = results.length; i < len; i++) {
                        if (results[i].geometry) {
                            geometry = results[i].geometry;
                            break;
                        }
                    }
                    for (i = 0, len = addressComponents.length; i < len; i++) {
                        if (addressComponents[i].types[0] === 'locality') {
                            locality = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'administrative_area_level_1') {
                            county = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'country') {
                            country = addressComponents[i].short_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'postal_code') {
                            zipCode = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'route') {
                            streetName = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'street_number') {
                            streetNumber = addressComponents[i].long_name.trim();
                        }
                    }

                    latitude = geometry.location.lat();
                    longitude = geometry.location.lng();

                    localityControl.value = locality;
                    countyControl.value = county;
                    countryControl.value = country;
                    zipCodeControl.value = zipCode;
                    streetNameControl.value = streetName;
                    streetNumberControl.value = streetNumber;
                    addressControl.value = streetName;
                    if (streetName && streetNumber) {
                    	addressControl.value += (', ' + streetNumber);
                    }
                    if (countryControl.className.indexOf('select2-hidden-accessible') !== -1) {
                        countryControl.dispatchEvent(new Event('change'));
                    }
                    latitudeControl.value =  latitude;
                    longitudeControl.value =  longitude;

                    var map = new google.maps.Map(document.getElementById('map-container'), {
                            zoom: 14,
                            center: new google.maps.LatLng(latitude, longitude),
                            mapTypeId: google.maps.MapTypeId.ROADMAP
                        }),
                        mapMarker = new google.maps.Marker({
                            position: new google.maps.LatLng(latitude, longitude),
                            draggable: true
                        });

                    google.maps.event.addListener(mapMarker, 'dragend', function (e) {
                        if (updateAddressCheckbox.checked) {
                            reverseGeocode(e.latLng);
                        }
                        latitudeControl.value = e.latLng.lat().toFixed(8);
                        longitudeControl.value = e.latLng.lng().toFixed(8);
                    });

                    mapMarker.setMap(map);
                    map.setCenter(mapMarker.position);
                }
            });
        };

        var reverseGeocode = function (latLng) {
            var geocoder = new google.maps.Geocoder();

            geocoder.geocode({location: latLng}, function (results, status) {
                if (status === google.maps.GeocoderStatus.OK) {
                    var addressComponents = [],
                        locality = '',
                        county = '',
                        country = '',
                        zipCode = '',
                        streetName = '',
                        streetNumber = '';

                    for (var i = 0, len = results.length; i < len; i++) {
                        if (results[i].address_components) {
                            addressComponents = results[i].address_components;
                            break;
                        }
                    }
                    for (i = 0, len = addressComponents.length; i < len; i++) {
                        if (addressComponents[i].types[0] === 'locality') {
                            locality = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'administrative_area_level_1') {
                            county = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'country') {
                            country = addressComponents[i].short_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'postal_code') {
                            zipCode = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'route') {
                            streetName = addressComponents[i].long_name.trim();
                        }
                        if (addressComponents[i].types[0] === 'street_number') {
                            streetNumber = addressComponents[i].long_name.trim();
                        }
                    }

                    localityControl.value = locality;
                    countyControl.value = county;
                    countryControl.value = country;
                    zipCodeControl.value = zipCode;
                    streetNameControl.value = streetName;
                    streetNumberControl.value = streetNumber;
                    addressControl.value = streetName;
                    if (streetName && streetNumber) {
                        addressControl.value += (', ' + streetNumber);
                    }
                    if (countryControl.className.indexOf('select2-hidden-accessible') !== -1) {
                        countryControl.dispatchEvent(new Event('change'));
                    }
                }
            });
        };

		window.initMap = function () {
            var map = new google.maps.Map(document.getElementById('map-container'), {
                    zoom: 14,
                    center: new google.maps.LatLng(defaultLat, defaultLng),
                    mapTypeId: google.maps.MapTypeId.ROADMAP
                }),
                mapMarker = new google.maps.Marker({
                    position: new google.maps.LatLng(defaultLat, defaultLng),
                    draggable: true
                });

            google.maps.event.addListener(mapMarker, 'dragend', function (e) {
                if (updateAddressCheckbox.checked) {
                    reverseGeocode(e.latLng);
                }
                latitudeControl.value = e.latLng.lat().toFixed(8);
                longitudeControl.value = e.latLng.lng().toFixed(8);
            });

            addressControl.addEventListener('change', function (e) {
                geocode(e.target.valueOf().value);
            });

            var addressGeocodeTimeout;
            addressControl.addEventListener('keyup', function (e) {
                // Debounce properly: the timeout handle must live outside the handler, otherwise
                // clearTimeout() never cancels the previous one and every keystroke fires a
                // geocode that overwrites what you are still typing.
                clearTimeout(addressGeocodeTimeout);
                var value = e.target.valueOf().value;
                addressGeocodeTimeout = setTimeout(function() {
                    geocode(value);
                }, 2000);
            });

            mapMarker.setMap(map);
            map.setCenter(mapMarker.position);
		};
	})(window, document);
</script>
<script async defer src="//maps.googleapis.com/maps/api/js?key=<?= Yii::$app->settings->get('googleMapKey') ?>&language=<?= Yii::$app->language ?>&callback=initMap"></script>

