<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Notification */

use common\helpers\FontIcon;
use backend\widgets\ActiveForm;
use tws\widgets\tinymce\TinyMCE;
use kartik\color\ColorInput;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<?= $form->field($model, 'title')->textInput() ?>
	<?= $form->field($model, 'message')->widget(TinyMCE::class) ?>

	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'icon')->widget(Select2::class, [
				'data' => FontIcon::getDropdownIcons(),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => true,
					'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?= $form->field($model, 'color')->widget(ColorInput::class, [
				'options' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
	</div>

	<?= $form->field($model, 'targeted_users')->widget(Select2::class, [
		'options' => [
			'size' => 1,
			'multiple' => true,
		],
		'data' => ArrayHelper::map(\common\models\User::findAllUsers(), 'uuid', 'fullName'),
		'maintainOrder' => false,
		'showToggleAll' => true,
		'toggleAllSettings' => [
			'selectLabel' => '<span class="glyphicon glyphicon-unchecked"></span> ' . Yii::t('common', 'Select All'),
			'unselectLabel' => '<span class="glyphicon glyphicon-check"></span> ' . Yii::t('common', 'Unselect All'),
		],
		'pluginLoading' => false,
		'pluginOptions' => [
			'placeholder' => Yii::t('common', 'Choose'),
		],
	])->hint(Yii::t('backend', 'Select all users that you want to receive this notification.')) ?>

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
