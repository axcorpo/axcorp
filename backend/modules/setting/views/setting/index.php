<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\GeneralSettingForm */
/* @var $form backend\widgets\ActiveForm */

use common\models\ScheduledTask;
use common\models\Setting;
use common\models\User;
use backend\widgets\ActiveForm;
use common\models\Currency;
use common\models\Country;
use tws\widgets\datetimepicker\DateTimePicker;
use kartik\file\FileInput;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Yii::t('backend', 'General Settings');
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
    <div class="row">
        <div class="col-sm-9">
            <?= $form->field($model, 'appName')->textInput() ?>
        </div>
        <div class="col-sm-3">
            <?= $form->field($model, 'translator')->inline(true)->checkboxList([1 => Yii::t('label', 'Translator'), 2 => Yii::t('label', 'Overwrite')]) ?>
        </div>
    </div>
	<?php
	$i18nFields = [];
	foreach (\common\models\Language::findAllLanguages() as $language) {
		$i18nFields[] = [
			'label' => mb_strtoupper($language->language),
			'content' => $form->field($model, "appDescription[{$language->language_id}]")->textarea(['rows' => 3]),
			'active' => $language->language_id === Yii::$app->language,
		];
	}
	?>
	<?= Tabs::widget([
		'items' => $i18nFields,
	]) ?>
	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'appLogo')->widget(FileInput::class, [
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
					'dropZoneEnabled' => true,
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
					'initialPreview' => $model->getAppLogoUrl() ?: false,
					'initialPreviewConfig' => [
						[
							'caption' => $model->appLogo,
							'downloadUrl' => $model->getAppLogoUrl(),
						],
					],
					'initialPreviewAsData' => true,
					'initialPreviewShowDelete' => true,
					'overwriteInitial' => true,
					'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
					'deleteExtraData' => [
						'attribute' => 'appLogo',
					],
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?= $form->field($model, 'appLogoAlt')->widget(FileInput::class, [
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
					'dropZoneEnabled' => true,
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
					'initialPreview' => $model->getAppLogoAltUrl() ?: false,
					'initialPreviewConfig' => [
						[
							'caption' => $model->appLogo,
							'downloadUrl' => $model->getAppLogoAltUrl(),
						],
					],
					'initialPreviewAsData' => true,
					'initialPreviewShowDelete' => true,
					'overwriteInitial' => true,
					'deleteUrl' => Url::to(['delete-file', 'id' => $model->id]),
					'deleteExtraData' => [
						'attribute' => 'appLogoAlt',
					],
				],
			]) ?>
		</div>
	</div>
	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('backend', 'Date And Time') ?></div>
		</div>
		<div class="panel-body">
			<div class="text-info-icon fa-info-circle text-muted margin-bottom-10"><?= Yii::t('backend', 'Available formats can be found in the {0} manual.', ['<a href="http://userguide.icu-project.org/formatparse/datetime" target="_blank">ICU</a>']) ?></div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'timeZone')->widget(Select2::class, [
						'data' => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()),
						'pluginLoading' => false,
						'pluginOptions' => [
							'placeholder' => Yii::t('common', 'Choose'),
						],
					])->hint(Yii::t('backend', 'The time zone to use for formatting time and date values.')) ?>
				</div>
				<div class="col-sm-6">
                    <?= $form->field($model, 'timeFormat')->widget(Select2::class, [
                        'data' => [
                            'HH:mm:ss' => 'HH:mm:ss',
                        ],
                        'pluginLoading' => false,
                        'pluginOptions' => [
                            'allowClear' => false,
                            'placeholder' => Yii::t('common', 'Choose'),
                        ],
                    ])->hint(Yii::t('backend', 'The default format string to be used to format a time.')) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
                    <?= $form->field($model, 'dateFormat')->widget(Select2::class, [
                        'data' => [
                            'dd-MM-yyyy' => 'dd-MM-yyyy',
                            'dd.MM.yyyy' => 'dd.MM.yyyy',
                            'dd/MM/yyyy' => 'dd/MM/yyyy',
                            'yyyy-MM-dd' => 'yyyy-MM-dd',
                            'yyyy.MM.dd' => 'yyyy.MM.dd',
                            'yyyy/MM/dd' => 'yyyy/MM/dd',
                            'MM-dd-yyyy' => 'MM-dd-yyyy',
                            'MM/dd/yyyy' => 'MM/dd/yyyy',
                        ],
                        'pluginLoading' => false,
                        'pluginOptions' => [
                            'allowClear' => false,
                            'placeholder' => Yii::t('common', 'Choose'),
                        ],
                    ])->hint(Yii::t('backend', 'The default format string to be used to format a date.')) ?>
				</div>
				<div class="col-sm-6">
                    <?= $form->field($model, 'datetimeFormat')->widget(Select2::class, [
                        'data' => [
                            'dd-MM-yyyy HH:mm:ss' => 'dd-MM-yyyy HH:mm:ss',
                            'dd.MM.yyyy HH:mm:ss' => 'dd.MM.yyyy HH:mm:ss',
                            'dd/MM/yyyy HH:mm:ss' => 'dd/MM/yyyy HH:mm:ss',
                            'yyyy-MM-dd HH:mm:ss' => 'yyyy-MM-dd HH:mm:ss',
                            'yyyy.MM.dd HH:mm:ss' => 'yyyy.MM.dd HH:mm:ss',
                            'yyyy/MM/dd HH:mm:ss' => 'yyyy/MM/dd HH:mm:ss',
                            'MM-dd-yyyy HH:mm:ss' => 'MM-dd-yyyy HH:mm:ss',
                            'MM/dd/yyyy HH:mm:ss' => 'MM/dd/yyyy HH:mm:ss',
                        ],
                        'pluginLoading' => false,
                        'pluginOptions' => [
                            'allowClear' => false,
                            'placeholder' => Yii::t('common', 'Choose'),
                        ],
                    ])->hint(Yii::t('backend', 'The default format string to be used to format a date and time.')) ?>
				</div>
			</div>
		</div>
	</div>

	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('common', 'Display') ?></div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'itemsPerPage')->widget(TouchSpin::class, [
						'pluginOptions' => [
							'min' => 1,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
						],
					])->hint(Yii::t('backend', 'The number of items listed per each page.')) ?>
				</div>
				<div class="col-sm-6">
					<?= $form->field($model, 'currencyCode')->widget(Select2::class, [
						'data' => ArrayHelper::map(Currency::findAllCurrencies(), 'iso_code', 'iso_code'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'placeholder' => Yii::t('common', 'Choose'),
						],
					])->hint(Yii::t('backend', 'The 3-letter ISO 4217 currency code indicating the default currency.')) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'defaultCountry')->widget(Select2::class, [
						'data' => ArrayHelper::map(Country::findAllCountries(), 'iso_alpha2', 'translation.name'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'placeholder' => Yii::t('common', 'Choose'),
						],
					])->hint(Yii::t('backend', 'The default value used to preselect the country in all dropdown menus.')) ?>
				</div>
				<div class="col-sm-6">
					<?= $form->field($model, 'defaultLanguage')->widget(Select2::class, [
						'data' => ArrayHelper::map(\common\models\Language::findAllLanguages(), 'language_id', 'name'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'placeholder' => Yii::t('common', 'Choose'),
						],
					])->hint(Yii::t('backend', 'The application default language.')) ?>
				</div>
			</div>
		</div>
	</div>

	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('common', 'User Account') ?></div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'userAccountActivation')->widget(Select2::class, [
						'options' => [
							'data' => [
								'toggle-visibility' => '#email-account-activation-fieldset',
								'toggle-visibility-val' => User::ACCOUNT_ACTIVATION_CONFIRMATION,
							],
						],
						'data' => User::getAccountActivationLabels(),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => false,
							'placeholder' => Yii::t('common', 'Choose'),
						],
					])->hint(Yii::t('backend', 'The default behavior for a new user account.')) ?>
				</div>
				<div class="col-sm-6">
					<?= $form->field($model, 'userPasswordResetTokenExpiration')->widget(TouchSpin::class, [
						'options' => [
							'placeholder' => Yii::t('common', 'Example') . ': 60',
						],
						'pluginOptions' => [
							'min' => 1,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
							'prefix' => Yii::t('common', 'After'),
							'postfix' => mb_strtolower(Yii::t('common', 'Minutes')),
						],
					])->hint(Yii::t('backend', 'Availability of the password reset code. Defaults to 60 minutes (1 hour).')) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'userLoginDuration')->widget(TouchSpin::class, [
						'options' => [
							'placeholder' => Yii::t('common', 'Example') . ': 30',
						],
						'pluginOptions' => [
							'min' => 0,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
							'postfix' => mb_strtolower(Yii::t('label', 'Days')),
						],
					])->hint(Yii::t('backend', 'Maximum allowed interval for the user to remain authenticated.')) ?>
				</div>
			</div>
		</div>
	</div>

    <div class="panel blue-hoki">
        <div class="panel-title">
            <div class="panel-heading"><?= Yii::t('common', 'Mobile Applications') ?></div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'appleStoreUrl')->textInput() ?>
                </div>
                <div class="col-sm-6">
                    <?= $form->field($model, 'googlePlayUrl')->textInput() ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'oblioClientId')->textInput() ?>
                </div>
                <div class="col-sm-6">
                    <?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
                    <?= $form->field($model, 'oblioClientSecret', ['template' => $secretFieldTemplate])->passwordInput(['value' => '', 'placeholder' => $model->oblioClientSecret ? Yii::t('backend', 'Unchanged. Type a new value to replace it.') : '']) ?>
                </div>
            </div>
        </div>
    </div>

	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('common', 'Other') ?></div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-12">
					<?= $form->field($model, 'enableEventLogs')->checkbox() ?>
				</div>
				<div class="col-sm-12">
					<?= $form->field($model, 'enableSoftDelete')->checkbox() ?>
				</div>
				<div class="col-sm-12">
					<?= $form->field($model, 'enableDocumentation')->checkbox() ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
					<?php
					$initialPreview = [];
					$initialPreviewConfig = [];
					if ($model->textToSpeechServiceAccountFile) {
						$url = Url::to("@backend/runtime/tts/{$model->textToSpeechServiceAccountFile}", true);
						$initialPreview[] = $url;
						$initialPreviewConfig[] = [
							'type'       => 'text',
							'caption'    => $model->textToSpeechServiceAccountFile,
							'downloadUrl'=> $url,
							'url'        => Url::to(['delete-tts-sa-file', 'id' => $model->id, 'file' => $model->textToSpeechServiceAccountFile]),
						];
					}
					?>

					<?= $form->field($model, 'textToSpeechServiceAccountFile')->widget(FileInput::class, [
						'options' => [
							'accept'   => '.json',
							'multiple' => false,
							'data' => [
								'operation-confirm' => Yii::t('common', 'Are you sure you want to delete this file?'),
							],
						],
						'pluginOptions' => [
							'allowedFileExtensions'   => ['json'],
							'maxFileSize'             => Yii::$app->settings->get('maxFileSize'),
							'dropZoneEnabled'         => true,
							'showClose'               => false,
							'showUpload'              => false,
							'showCaption'             => true,
							'showRemove'              => true,
							'showPreview'             => true,
							'hideThumbnailContent'    => false,
							'fileActionSettings'      => [
								'showDownload' => true,
								'showRemove'   => true,
								'showUpload'   => false,
								'showZoom'     => false,
								'showDrag'     => false,
							],
							'initialPreview'          => $initialPreview,
							'initialPreviewConfig'    => $initialPreviewConfig,
							'initialPreviewAsData'    => true,
							'initialPreviewShowDelete'=> true,
							'overwriteInitial'        => true,
						],
					]) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'enableTwoFactorAuthentication')->checkbox() ?>
				</div>
				<div class="col-sm-6">
					<?= $form->field($model, 'twoFactorAuthenticationDuration')->widget(TouchSpin::class, [
						'options' => [
							'placeholder' => Yii::t('common', 'Example') . ': 30',
						],
						'pluginOptions' => [
							'min' => 0,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
							'postfix' => mb_strtolower(Yii::t('label', 'Days')),
						],
					])->hint(Yii::t('backend', 'Maximum allowed interval for the user to authenticate after usage of a 2FA token.')) ?>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<?= $form->field($model, 'googleMapKey', ['template' => $secretFieldTemplate])->passwordInput([
						'value' => '',
						'placeholder' => $model->googleMapKey
							? Yii::t('backend', 'Unchanged. Type a new value to replace it.')
							: Yii::t('common', 'Example') . ': AIzaSyDaHTRBC6R902Amq',
					]) ?>
				</div>
				<div class="col-sm-6">
					<?= $form->field($model, 'maxFileSize')->widget(TouchSpin::class, [
						'options' => [
							'placeholder' => Yii::t('common', 'Example') . ': 1024',
						],
						'pluginOptions' => [
							'min' => 5,
							'max' => PHP_INT_MAX,
							'step' => 1,
							'decimals' => 0,
							'boostat' => 5,
							'maxboostedstep' => 10,
							'verticalbuttons' => true,
							'postfix' => 'KB',
						],
					])->hint(Yii::t('backend', 'The maximum file size in kilobytes for uploaded files.') . ' 1MB = 1024KB.') ?>
				</div>
			</div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'reCaptchaSiteKey', ['template' => $secretFieldTemplate])->passwordInput([
                        'placeholder' => Yii::t('common', 'Example') . ': 6LcXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                    ]) ?>
                </div>
                <div class="col-sm-6">
                    <?= $form->field($model, 'reCaptchaSecretKey', ['template' => $secretFieldTemplate])->passwordInput([
                        'value' => '',
                        'placeholder' => $model->reCaptchaSecretKey
                            ? Yii::t('backend', 'Unchanged. Type a new value to replace it.')
                            : Yii::t('common', 'Example') . ': 6LcYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY',
                    ]) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'vatRate')->widget(TouchSpin::class, [
                        'pluginOptions' => [
                            'min' => 0,
                            'max' => PHP_INT_MAX,
                            'step' => 1,
                            'decimals' => 0,
                            'boostat' => 5,
                            'maxboostedstep' => 10,
                            'verticalbuttons' => true,
                            'postfix' => '%',
                        ],
                    ]) ?>
                </div>
            </div>
			<div>
				<label class="control-label"><?= Yii::t('backend', 'Automatic Exchange Rate Update Scheduling') ?></label>
				<div class="row">
					<div class="col-lg-5">
						<?php $periodField = $form->field($model, 'exchangeRateCycle', [
							'options' => [
								'class' => 'col-xs-6',
							],
							'template' => '{input}',
						])->widget(Select2::class, [
							'options' => [
								'data' => [
									'toggle-visibility' => '',
									'toggle-visibility-val' => [
										ScheduledTask::CYCLE_WEEK => '.exchange-rate-day',
										ScheduledTask::CYCLE_MONTH => '.exchange-rate-day',
										ScheduledTask::CYCLE_YEAR => '.exchange-rate-month, .exchange-rate-day',
									],
								],
							],
							'data' => \common\models\Package::getCycleLabels($model->exchangeRatePeriod > 1 ? 'plural' : null),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
						<?= $form->field($model, 'exchangeRatePeriod', [
							'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div>' . $periodField . '</div>{error}{hint}',
						])->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 1,
								'max' => PHP_INT_MAX,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
								'prefix' => Yii::t('common', 'At Each'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-3 exchange-rate-month <?= $model->exchangeRateCycle == 'year' ? '' : 'hidden' ?>">
						<?= $form->field($model, 'exchangeRateMonth')->widget(Select2::class, [
							'addon' => [
								'prepend' => [
									'content' => Yii::t('label', 'Month'),
								]
							],
							'data' => Setting::getMonthsOfYear(),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-2 exchange-rate-day <?= $model->exchangeRateCycle != 'day' ? '' : 'hidden' ?>">
						<?= $form->field($model, 'exchangeRateDay')->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 1,
								'max' => 31,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
								'prefix' => Yii::t('label', 'Day'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-2">
						<?= $form->field($model, 'exchangeRateTime')->widget(DateTimePicker::class, [
							'options' => [
								'placeholder' => 'HH:mm',
							],
							'inputAddonContent' => Yii::t('label', 'Time'),
							'clientOptions' => [
								'format' => 'HH:mm',
								'ignoreReadonly' => true,
								'showTodayButton' => true,
								'showClear' => true,
								'showClose' => true,
								'allowInputToggle' => true,
								'useCurrent' => false,
								'widgetParent' => 'body',
							],
						])->label(false) ?>
					</div>
				</div>
			</div>
			<div>
				<label class="control-label"><?= Yii::t('backend', 'Automatic Backup Scheduling') ?></label>
				<div class="row">
					<div class="col-lg-5">
						<?php $periodField = $form->field($model, 'backupCycle', [
							'options' => [
								'class' => 'col-xs-6',
							],
							'template' => '{input}',
						])->widget(Select2::class, [
							'options' => [
								'data' => [
									'toggle-visibility' => '',
									'toggle-visibility-val' => [
										ScheduledTask::CYCLE_WEEK => '.backup-day',
										ScheduledTask::CYCLE_MONTH => '.backup-day',
										ScheduledTask::CYCLE_YEAR => '.backup-month, .backup-day',
									],
								],
							],
							'data' => \common\models\Package::getCycleLabels($model->backupPeriod > 1 ? 'plural' : null),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						]) ?>
						<?= $form->field($model, 'backupPeriod', [
							'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div>' . $periodField . '</div>{error}{hint}',
						])->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 1,
								'max' => PHP_INT_MAX,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
								'prefix' => Yii::t('common', 'At Each'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-3 backup-month <?= $model->backupCycle == 'year' ? '' : 'hidden' ?>">
						<?= $form->field($model, 'backupMonth')->widget(Select2::class, [
							'addon' => [
								'prepend' => [
									'content' => Yii::t('label', 'Month'),
								]
							],
							'data' => Setting::getMonthsOfYear(),
							'pluginLoading' => false,
							'pluginOptions' => [
								'allowClear' => false,
								'placeholder' => Yii::t('common', 'Choose'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-2 backup-day <?= $model->backupCycle != 'day' ? '' : 'hidden' ?>">
						<?= $form->field($model, 'backupDay')->widget(TouchSpin::class, [
							'pluginOptions' => [
								'min' => 1,
								'max' => 31,
								'step' => 1,
								'decimals' => 0,
								'boostat' => 5,
								'maxboostedstep' => 10,
								'verticalbuttons' => true,
								'prefix' => Yii::t('label', 'Day'),
							],
						])->label(false) ?>
					</div>
					<div class="col-lg-2">
						<?= $form->field($model, 'backupTime')->widget(DateTimePicker::class, [
							'options' => [
								'placeholder' => 'HH:mm',
							],
							'inputAddonContent' => Yii::t('label', 'Time'),
							'clientOptions' => [
								'format' => 'HH:mm',
								'ignoreReadonly' => true,
								'showTodayButton' => true,
								'showClear' => true,
								'showClose' => true,
								'allowInputToggle' => true,
								'useCurrent' => false,
								'widgetParent' => 'body',
							],
						])->label(false) ?>
					</div>
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
