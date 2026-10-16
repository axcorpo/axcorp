<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Workspace */

use common\models\Workspace;
use backend\widgets\ActiveForm;
use kartik\depdrop\DepDrop;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use tws\helpers\Url;

?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'status')->widget(Select2::class, [
				'data' => ArrayHelper::getColumn(Workspace::getStatusLabels(), 'label'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?= $form->field($model, 'type')->widget(Select2::class, [
				'data' => Workspace::getTypeLabels(),
				'pluginLoading' => false,
				'pluginOptions' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			])->hint(Yii::t('backend', 'Selects the taxonomy seeded into the workspace database at install/reinstall.')) ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'subscriber_id')->widget(Select2::class, [
				'data' => ArrayHelper::map(\common\models\Subscriber::findAllSubscribers(), 'uuid', 'user.fullName'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'allowClear' => false,
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?php echo Html::hiddenInput('selected_id', $model->isNewRecord ? '' : $model->uuidOf('subscription_id'), ['id'=>'selected_id']); ?>
			<?= $form->field($model, 'subscription_id')->widget(DepDrop::class, [
				'data' => ArrayHelper::map(\common\models\Subscription::findAvailableSubscriptions(!$model->isNewRecord ? $model->subscription->uuidOf('subscriber_id') : null, !$model->isNewRecord ? false : true), 'uuid', 'formattedName'),
				'type' => DepDrop::TYPE_SELECT2,
				'select2Options' => [
					'maintainOrder' => false,
					'showToggleAll' => false,
					'pluginLoading' => false,
					'pluginOptions' => [
						'allowClear' => false,
						'placeholder' => Yii::t('common', 'Choose'),
					],
				],
				'pluginOptions' => [
					'url' => Url::to(['/subscriber-manager/subscription/subscriber-subscriptions']),
					'depends' => [Html::getInputId($model, 'subscriber_id')],
					'initialize' => $model->isNewRecord ? false : true,
					'loading' => true,
					'loadingText' => Yii::t('common', 'Loading...'),
					'placeholder' => Yii::t('common', 'Choose'),
					'params'=> ['selected_id'],
				],
			]) ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<?= $form->field($model, 'url', [
				'template' => '{label}<div class="input-group"><div class="input-group-addon">' . Yii::$app->request->hostInfo . '/</div>{input}</div>{hint}{error}',
			])->textInput(['class' => 'form-control text-lowercase'])->hint(Yii::t('backend', 'The full domain with extension, e.g. client.ro')) ?>
		</div>
        <div class="col-sm-6">
			<?= $form->field($model, 'domain')->textInput(['class' => 'form-control text-lowercase'])->hint(Yii::t('backend', 'The full addon domain with extension, e.g. client.ro')) ?>
        </div>
		<?php if ($model->isNewRecord): ?>
        <div class="col-sm-6">
			<?= $form->field($model, 'code')->textInput()->hint(Yii::t('backend', 'Also used as the workspace database name. Can only be set at creation.')) ?>
        </div>
		<?php endif; ?>
	</div>
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
