<?php

/* @var $this yii\web\View */
/* @var $model backend\modules\setting\models\ScriptSettingForm */
/* @var $form backend\widgets\ActiveForm */

use backend\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = Yii::t('backend', 'Script Settings');
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


	<div class="panel blue-hoki">
		<div class="panel-title">
			<div class="panel-heading"><?= Yii::t('backend', 'Head') ?></div>
		</div>
		<div class="panel-body">
			<?= $form->field($model, 'headContent', [
				'options' => [
					'id' => Html::getInputId($model, 'headContent') . '-form-group',
				],
			])->textarea([
				'rows' => 8,
			])->hint(Yii::t('backend', 'Scripts that go in head of the website.')) ?>
		</div>
	</div>

    <div class="panel blue-hoki">
        <div class="panel-title">
            <div class="panel-heading"><?= Yii::t('backend', 'Footer') ?></div>
        </div>
        <div class="panel-body">
            <?= $form->field($model, 'footerContent', [
                'options' => [
                    'id' => Html::getInputId($model, 'footerContent') . '-form-group',
                ],
            ])->textarea([
                'rows' => 8,
            ])->hint(Yii::t('backend', 'Scripts that go in footer of the website.')) ?>
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
