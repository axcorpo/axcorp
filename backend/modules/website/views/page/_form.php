<?php

/* @var $this yii\web\View */
/* @var $form backend\widgets\ActiveForm */
/* @var $model common\models\Page */

use common\models\Page;
use backend\widgets\ActiveForm;
use kartik\select2\Select2;
use yii\bootstrap\Tabs;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

?>

<?php $form = ActiveForm::begin([
	'id' => 'page-form',
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
            <?php if ($model->default != Page::YES): ?>
                <div class="row">
                    <div class="col-sm-6">
                        <?= $form->field($model, 'status')->widget(Select2::class, [
                            'data' => ArrayHelper::getColumn(Page::getStatusLabels(), 'label'),
                            'pluginLoading' => false,
                            'pluginOptions' => [
                                'placeholder' => Yii::t('common', 'Choose'),
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>
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
