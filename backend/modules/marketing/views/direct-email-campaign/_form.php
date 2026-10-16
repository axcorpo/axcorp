<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model backend\modules\marketing\models\DirectMarketingCampaign */
/* @var $searchModel common\models\MarketingRecipientSearchForm */

use backend\modules\marketing\models\DirectMarketingCampaign;
use backend\widgets\ActiveForm;
use kartik\select2\Select2;
use kartik\touchspin\TouchSpin;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
?>

<?php $form = ActiveForm::begin([
	'id' => mb_strtolower($model->formName()),
	'options' => [
		'novalidate' => true,
	],
	'validateOnType' => true,
]); ?>
	<div class="panel-group accordion scrollable" id="content-accordion" role="tablist" aria-multiselectable="true">
		<div class="panel blue-hoki">
			<div class="panel-heading" role="tab" id="heading-0">
				<div class="panel-title bold">
					<a class="accordion-toggle accordion-toggle-styled <?= $model->isNewRecord ? '' : 'collapsed' ?>" role="button" data-toggle="collapse" href="#collapse-0" aria-expanded="true" aria-controls="collapse-0">
						<?= Yii::t('common', 'Filters') ?>
					</a>
				</div>
			</div>
			<div id="collapse-0" class="panel-collapse collapse <?= $model->isNewRecord ? 'in' : '' ?>" role="tabpanel" aria-labelledby="heading-0">
				<div class="panel-body">
					<?= $this->render('/_shared/_search-fields', [
						'form' => $form,
						'model' => $searchModel,
					]) ?>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-sm-3">
			<?= $form->field($model, 'status')->widget(Select2::class, [
				'data' => ArrayHelper::getColumn(DirectMarketingCampaign::getStatusLabels(), 'label'),
				'pluginLoading' => false,
				'pluginOptions' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
		</div>
		<div class="col-sm-6">
			<?php $cycleField = $form->field($model, 'cycle', [
				'options' => [
					'class' => 'col-xs-6',
				],
				'template' => '{input}',
			])->widget(Select2::class, [
				'data' => \common\models\ScheduledTask::getCycleLabels(),
				'pluginLoading' => false,
				'pluginOptions' => [
					'placeholder' => Yii::t('common', 'Choose'),
				],
			]) ?>
			<?= $form->field($model, 'frequency', [
				'template' => '{label}<div class="row row-no-gutters row-custom"><div class="col-xs-6">{input}</div><div class="col-addon">/</div>' . $cycleField . '</div>{error}{hint}',
			])->widget(TouchSpin::class, [
				'pluginOptions' => [
					'min' => 1,
					'max' => PHP_INT_MAX,
					'step' => 1,
					'decimals' => 0,
					'boostat' => 5,
					'maxboostedstep' => 10,
					'verticalbuttons' => true,
					'postfix' => mb_strtolower($model->frequency == 1 ? Yii::t('label', 'Message') : Yii::t('label', 'Messages')),
				],
			])->hint(Yii::t('backend', 'The number of messages to be sent at an interval.')) ?>
		</div>
	</div>

    <div class="row">
        <div class="col-sm-3">
            <?= $form->field($model, 'translator')->inline(true)->checkboxList([1 => Yii::t('label', 'Translator'), 2 => Yii::t('label', 'Overwrite')]) ?>
        </div>
    </div>

	<?php
		$i18nFields = [];
		foreach (\common\models\Language::findAllLanguages() as $language) {
			$i18nFields[] = [
				'label' => mb_strtoupper($language->language),
				'content' => $this->render('_i18n-fields', [
					'model' => $model,
					'form' => $form,
					'language' => $language,
				]),
				'active' => $language->language_id === Yii::$app->language,
			];
		}
	?>
	<?= Tabs::widget([
		'items' => $i18nFields,
	]) ?>

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
