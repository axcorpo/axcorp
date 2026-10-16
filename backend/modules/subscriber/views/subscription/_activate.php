<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Subscription */

use backend\widgets\ActiveForm;
use common\models\Company;
use common\models\Feature;
use common\models\Package;
use common\models\FeatureModule;
use common\models\ScheduledTask;
use common\models\Subscriber;use common\models\Subscription;
use kartik\number\NumberControl;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use tws\widgets\datetimepicker\DateTimePicker;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
?>


<?php $form = ActiveForm::begin([
	'id' => 'subscription-form',
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
				<div class="modal-title"><?= Yii::t('backend', 'Issue invoice') ?></div>
			</div>
		<?php endif; ?>
		<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
            <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'end_at')->widget(DateTimePicker::class, [
                        'id' => 'dp-end_at',
                        'options' => [
                            'value' => $model->end_at ? Yii::$app->formatter->asDatetime($model->end_at) : null,
                            'placeholder' => Yii::$app->settings->get('datetimeFormat'),
                        ],
                        'clientOptions' => [
                            'format' => 'icu:' . Yii::$app->settings->get('datetimeFormat'),
                            'minDate' => (new DateTime)->format(DATE_ATOM),
                            'ignoreReadonly' => true,
                            'showTodayButton' => true,
                            'showClear' => true,
                            'showClose' => true,
                            'allowInputToggle' => true,
                            'useCurrent' => false,
                        ],
                    ]) ?>
                </div>
                <div class="col-sm-6">
                    <?= $form->field($model, 'issue_invoice')->checkbox([
                        'data' => [
                            'toggle-visibility' => '.tv-companies',
                            'toggle-visibility-val' => '1',
                        ],
                    ]) ?>
                </div>
            </div>
            <?php
            $subscription = Subscription::findOne(['id' => $model->id]);
            $subscriber = Subscriber::findOne(['id' => $subscription->subscriber_id]);
            $company = Company::findAll(['user_id' => $subscriber->user->id]);
            ?>
            <div class="row tv-companies <?= $model->issue_invoice == 0 ? ' hidden' : '' ?>">
                <div class="col-sm-12">
                    <?= $form->field($model, 'company')->widget(Select2::class, [
                        'options' => [
                            'multiple' => false,
                        ],
                        'data' => ArrayHelper::map($company, 'uuid', 'name'),
                        'maintainOrder' => true,
                        'showToggleAll' => false,
                        'pluginLoading' => false,
                        'pluginOptions' => [
                            'allowClear' => true,
                            'placeholder' => Yii::t('common', 'Choose'),
                        ],
                    ]) ?>
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
