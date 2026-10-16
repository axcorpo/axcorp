<?php
/* @var $this yii\web\View */
/* @var $model frontend\modules\account\models\ProfileForm */
/* @var $passwordForm frontend\modules\account\models\PasswordForm */

use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use common\models\User;
use common\widgets\ActiveForm;
use tws\widgets\datetimepicker\DateTimePicker;
use kartik\file\FileInput;
use kartik\select2\Select2;
use tws\widgets\typeahead\Typeahead;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;
use tws\helpers\Url;

$this->params['breadcrumbs'][] = Html::encode($this->title);
?>

<div class="tab-pane fade shadow show active rounded p-4" id="account" role="tabpanel" aria-labelledby="account">
    <?php $form = ActiveForm::begin([
        'id' => mb_strtolower($model->formName()),
        'options' => [
            'class' => 'profile-form',
            'novalidate' => true,
        ],
        'fieldConfig' => [
            'options' => ['class' => 'mb-3'],
            'labelOptions' => ['class' => 'form-label'],
        ],
        'validateOnType' => true,
        'validateOnBlur' => false,
    ]); ?>

        <?php if ($model->hasErrors() && empty(array_intersect_key($model->errors, $model->attributes))): ?>
            <?= $form->errorSummary($model, [
                'header' => false,
                'class' => 'alert alert-danger alert-icon',
            ]) ?>
        <?php endif; ?>

        <div class="row align-items-center mb-2">
            <div class="col-md-4">
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
                        'browseClass' => 'btn btn-outline-primary w-100',
                        'browseIcon' => '<i class="uil uil-camera"></i> ',
                        'browseLabel' => Yii::t('common', 'Change Image'),
                        'uploadUrl' => Url::to(['/account/profile/upload-file']),
                        'uploadExtraData' => [
                            'attribute' => 'image',
                            'fileName' => "{$model->formName()}[imageFile]",
                        ],
                        'showUploadedThumbs' => true,
                        'showAjaxErrorDetails' => false,
                        'previewClass' => 'file-preview-custom',
                        'frameClass' => 'file-preview-frame-custom',
                        'layoutTemplates' => [
                            'footer' => '',
                            'progress' => '',
                        ],
                        'dropZoneEnabled' => false,
                        'showClose' => false,
                        'showUpload' => false,
                        'showCaption' => false,
                        'showRemove' => false,
                        'showCancel' => false,
                        'showPreview' => true,
                        'fileActionSettings' => [
                            'showDownload' => false,
                            'showRemove' => false,
                            'showUpload' => false,
                            'showZoom' => false,
                            'showDrag' => false,
                        ],
                        'initialPreview' => $model->imageUrl ?: Url::to('@web/img/img-placeholder-user.png'),
                        'initialPreviewAsData' => true,
                        'overwriteInitial' => true,
                    ],
                    'pluginEvents' => [
                        'filebatchselected' => new \yii\web\JsExpression('function () {
                            $(this).fileinput("upload");
                        }'),
                    ],
                ]) ?>
            </div>
        </div>

        <h5 class="mt-3 mb-3"><?= Yii::t('common', 'Personal Data') ?></h5>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'last_name')->textInput(['class' => 'form-control']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'first_name')->textInput(['class' => 'form-control']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'middle_name')->textInput(['class' => 'form-control']) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'phone')->widget(PhoneInput::class, [
                    'jsOptions' => PhoneInputConfig::jsOptions(),
                    'options' => PhoneInputConfig::inputClassOptions(),
                ]) ?>
            </div>
            <?php if ($model->hasSubscriberProfile): ?>
            <div class="col-md-4">
                <?= $form->field($model, 'date_of_birth')->widget(DateTimePicker::class, [
                    'options' => [
                        'class' => 'form-control',
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
            <?php endif; ?>
            <div class="col-md-4">
                <?php $genderInputId = Html::getInputId($model, 'gender'); ?>
                <?= $form->field($model, 'gender')->radioList(User::getGenderLabels(), [
                    'item' => function ($index, $label, $name, $checked, $value) use ($genderInputId) {
                        $id = $genderInputId . '-' . $index;
                        $checked = $checked ? 'checked' : '';
                        return '<div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="radio" name="' . $name . '" id="' . $id . '" value="' . $value . '" ' . $checked . '>
                            <label class="form-check-label" for="' . $id . '">' . $label . '</label>
                        </div>';
                    },
                    'class' => 'form-check-group d-flex flex-wrap',
                ]) ?>
            </div>
        </div>

        <?php // A pure affiliate has no subscriber row to hold these (ProfileForm::getHasSubscriberProfile()). ?>
        <?php if ($model->hasSubscriberProfile): ?>
        <h5 class="mt-3 mb-3"><?= Yii::t('label', 'Address') ?></h5>
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
                    ],
                ]) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-3">
                <?= $form->field($model, 'zip_code')->textInput(['class' => 'form-control']) ?>
            </div>
            <div class="col-sm-9">
                <?= $form->field($model, 'address')->textInput(['class' => 'form-control']) ?>
            </div>
        </div>

        <?php endif; ?>

        <h5 class="mt-3 mb-3"><?= Yii::t('common', 'Notifications') ?></h5>
        <?= $form->field($model, 'marketing_recipient', [
            'options' => ['class' => 'mb-3'],
            'template' => '<div class="form-check">{input} <label class="form-check-label" for="' . Html::getInputId($model, 'marketing_recipient') . '">' . $model->getAttributeLabel('marketing_recipient') . '</label></div>{error}',
        ])->checkbox(['class' => 'form-check-input'], false) ?>

        <div class="row">
            <div class="col-lg-12 mt-2 mb-0">
                <?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-primary']) ?>
            </div><!--end col-->
        </div>

    <?php ActiveForm::end(); ?>

    <?php $passwordActiveForm = ActiveForm::begin([
        'id' => mb_strtolower($passwordForm->formName()),
        'options' => [
            'class' => 'password-form mt-4',
            'novalidate' => true,
        ],
        'fieldConfig' => [
            'options' => ['class' => 'mb-3'],
            'labelOptions' => ['class' => 'form-label'],
        ],
        'validateOnType' => true,
        'validateOnBlur' => false,
    ]); ?>

        <h5 class="mt-3 mb-3"><?= Yii::t('common', 'Reset Password') ?></h5>
        <div class="row">
            <div class="col-sm-4">
                <?= $passwordActiveForm->field($passwordForm, 'new_password')->passwordInput(['class' => 'form-control', 'value' => '', 'autocomplete' => 'new-password']) ?>
            </div>
            <div class="col-sm-4">
                <?= $passwordActiveForm->field($passwordForm, 'new_password_confirm')->passwordInput(['class' => 'form-control', 'value' => '', 'autocomplete' => 'new-password']) ?>
            </div>
            <div class="col-sm-4">
                <?= $passwordActiveForm->field($passwordForm, 'current_password')->passwordInput(['class' => 'form-control', 'value' => '', 'autocomplete' => 'current-password']) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 mt-2 mb-0">
                <?= Html::submitButton(Yii::t('common', 'Reset Password'), ['class' => 'btn btn-primary']) ?>
            </div><!--end col-->
        </div>

    <?php ActiveForm::end(); ?>
</div><!--end tab pane-->
