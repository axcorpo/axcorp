<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model backend\models\UserProfileForm */

use backend\widgets\ActiveForm;
use borales\extensions\phoneInput\PhoneInput;
use common\components\PhoneInputConfig;
use kartik\file\FileInput;
use yii\helpers\Html;
use tws\helpers\Url;

$this->title = Yii::t('common', 'Profile');
$this->params['breadcrumbs'][] = $this->title;
?>

<?php $form = ActiveForm::begin([
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-md-12 col-lg-4">
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
					'defaultPreviewContent' => Html::img($model->imageUrl ?: Url::to("@web/img/img-placeholder-user.png"), ['class' => 'img-responsive', 'alt' => '']),
					'initialPreview' => $model->image ? $model->imageUrl : false,
					'initialPreviewConfig' => [
						[
							'downloadUrl' => $model->imageUrl,
						],
					],
					'initialPreviewAsData' => true,
					'initialPreviewShowDelete' => true,
					'overwriteInitial' => true,
					'deleteUrl' => Url::to(['delete-file', 'id' => $model->id])
				],
			]) ?>
		</div>
	</div>

	<fieldset class="fieldset">
		<legend><?= Yii::t('common', 'Personal Data') ?></legend>
		<?= $form->field($model, 'gender')->inline()->radioList(\common\models\User::getGenderLabels()) ?>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'first_name')->textInput() ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'middle_name')->textInput() ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'last_name')->textInput() ?>
			</div>
		</div>
	</fieldset>

	<fieldset class="fieldset">
		<legend><?= Yii::t('common', 'Account') ?></legend>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'email')->input('email', ['disabled' => true]) ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'phone')->widget(PhoneInput::class, [
					'jsOptions' => PhoneInputConfig::jsOptions(),
					'options' => array_merge(PhoneInputConfig::inputClassOptions(), [
						'disabled' => true,
					]),
				]) ?>
			</div>
		</div>
		<?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'current_password', ['template' => $secretFieldTemplate])->passwordInput(['value' => '', 'autocomplete' => 'current-password'])->hint(Yii::t('backend', 'Required in order to change the password.')) ?>
				<?= $form->field($model, 'new_password', ['template' => $secretFieldTemplate])->passwordInput(['value' => '']) ?>
			</div>
			<div class="col-sm-4">
				<?= $form->field($model, 'new_password_confirm', ['template' => $secretFieldTemplate])->passwordInput() ?>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-4">
				<?= $form->field($model, 'role')->textInput(['disabled' => true]) ?>
			</div>
		</div>
	</fieldset>

	<div class="form-actions floating">
		<?= Html::submitButton('<span class="fa fa-check"></span>', [
			'class' => 'btn btn-xlg btn-fab btn-success',
			'title' => Yii::t('common', 'Save'),
			'data' => [
				'toggle' => 'tooltip',
			]
		]) ?>
	</div>
<?php ActiveForm::end(); ?>
