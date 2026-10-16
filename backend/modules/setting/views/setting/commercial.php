<?php
/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\CommercialSettingForm */
/* @var $form backend\widgets\ActiveForm */

use backend\modules\setting\models\CommercialSettingForm;
use backend\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = Yii::t('backend', 'Commercial Settings');
$this->params['breadcrumbs'] = [
	[
		'label' => Yii::t('common', 'Settings'),
		'url' => ['index'],
	],
	Yii::t('common', 'Commercial'),
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
			<div class="panel-heading"><?= Yii::t('backend', 'Company API Integrations') ?></div>
		</div>
		<div class="panel-body">
			<div class="panel panel-default">
				<div class="panel-heading">
					<?= $form->field($model, 'defaultGateway', [
						'radioTemplate' => '{beginLabel}{input}{labelTitle}<span></span>{endLabel}{error}{hint}',
						'options' => [
							'tag' => false,
						],
					])->radio([
						'id' => Html::getInputId($model, 'defaultGateway') . '-' . CommercialSettingForm::GATEWAY_OPENAPI,
						'value' => CommercialSettingForm::GATEWAY_OPENAPI,
						'label' => 'Open API',
						'labelOptions' => [
							'class' => 'mt-radio mt-radio-outline m0',
						],
						'uncheck' => null,
					]) ?>
				</div>
				<div class="panel-body">
					<div class="row">
						<div class="col-sm-6">
							<?= $form->field($model, 'openApiBaseUrl')->input('url')->hint(Yii::t('backend', 'The API base URL.') . ' ' . Yii::t('common', 'Example') . ': https://api.openapi.ro/api') ?>
						</div>
					</div>
					<?php $secretFieldTemplate = '{label}<div class="input-secret">{input}<span class="toggle-secret fa fa-eye-slash"></span></div>{hint}{error}'; ?>
					<?= $form->field($model, 'openApiKey', ['template' => $secretFieldTemplate])->textarea(['rows' => 2, 'class' => 'form-control secret-hidden', 'value' => '', 'placeholder' => $model->openApiKey ? Yii::t('backend', 'Unchanged. Type a new value to replace it.') : ''])->hint(Yii::t('backend', 'The API key.') . ' ' . Yii::t('common', 'Example') . ': abcdefghijklmnopqrstuvwxyz') ?>
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
