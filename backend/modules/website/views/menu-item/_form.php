<?php
/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\MenuItem */

use common\models\MenuItem;
use backend\widgets\ActiveForm;
use kartik\select2\Select2;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;
?>

<?php $form = ActiveForm::begin([
	'id' => 'menu-item-form',
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
				<div class="modal-title"><?= $this->title ?></div>
			</div>
		<?php endif; ?>

		<div class="form-fields <?= Yii::$app->request->isAjax ? 'modal-body' : '' ?>">
			<div class="row">
				<div class="col-sm-4">
					<?= $form->field($model, 'status')->widget(Select2::class, [
						'data' => ArrayHelper::getColumn(MenuItem::getStatusLabels(), 'label'),
						'pluginLoading' => false,
						'pluginOptions' => [
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
				<div class="col-sm-4">
					<?= $form->field($model, 'icon')->widget(Select2::class, [
						'data' => \common\helpers\FontIcon::getDropdownIcons(),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => true,
							'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
							'placeholder' => Yii::t('common', 'Choose'),
						],
					]) ?>
				</div>
			</div>
			<div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'target')->widget(Select2::class, [
                        'data' => [
                            '_blank' => Yii::t('common', 'New Tab'),
                        ],
                        'pluginLoading' => false,
                        'pluginOptions' => [
                            'allowClear' => true,
                            'placeholder' => Yii::t('common', 'Choose'),
                        ],
                    ]) ?>
                </div>
				<div class="col-sm-6">
					<?= $form->field($model, 'page_id')->widget(Select2::class, [
						'options' => [
							'data' => [
								'toggle-visibility' => '.page-selected-hint',
								'toggle-visibility-val' => ':empty',
							],
						],
						'data' => ArrayHelper::map(\common\models\Page::findAllPages(), 'uuid', 'translation.title', function ($page) {
							return ucfirst($page->module) ?: 'Application';
						}),
						'pluginLoading' => false,
						'pluginOptions' => [
							'allowClear' => true,
							'placeholder' => Yii::t('common', 'Choose'),
						],
						'pluginEvents' => [
							'change' => new JsExpression('function (e) {
								$(".required-mark-target").toggleClass("required", e.target.value === "");
							}'),
						],
					]) ?>
				</div>
				<div class="col-sm-4 hidden">
					<div class="control-label hidden-xs">&nbsp;</div>
					<?= $form->field($model, 'excluded')->checkbox()->hint(Yii::t('common', 'Check this if the item is not visible in menu.')) ?>
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
				'id' => 'tabs-menu-item-translations',
				'items' => $i18nFields,
			]) ?>
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
