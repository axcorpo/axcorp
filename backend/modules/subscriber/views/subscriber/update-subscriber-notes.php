<?php
/* @var $this yii\web\View */
/* @var $model common\models\Subscriber */
/* @var $steps array */

use backend\widgets\ActiveForm;
use yii\helpers\Html;

?>

<?php $form = ActiveForm::begin([
	'id' => 'subscriber-notes-form',
	'action' => ['update-subscriber-notes', 'id' => $model->id],
	'method' => 'POST',
	'options' => [
		'class' => 'modal-dialog',
	],
]); ?>
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<div class="modal-title"><?= Yii::t('common', 'Update Internal Notes For {item}', ['item' => Yii::t('label', 'Subscriber')]) ?> #<?= $model->user->fullName ?></div>
		</div>
		<div class="modal-body">
			<?= $form->errorSummary($model, ['header' => false, 'class' => 'alert alert-danger']) ?>

            <?= $form->field($model, 'notes')->textarea(['rows' => 3])->label(false) ?>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('common', 'Cancel') ?></button>
			<?= Html::submitButton(Yii::t('common', 'Save'), ['class' => 'btn btn-success']) ?>
		</div>
	</div>
<?php ActiveForm::end(); ?>
