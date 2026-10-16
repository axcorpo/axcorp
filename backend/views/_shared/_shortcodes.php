<?php

/* @var $shortCodes array */
/* @var $columns int */
/* @var $insertTarget string */

use yii\helpers\Html;

if ($shortCodesCount = count($shortCodes)) {
	$bsMaxGridSize = 12;

	if (empty($columns)) {
		$columns = 2;
	}
	if ($columns > $shortCodesCount) {
		$columns = $shortCodesCount;
	}
	if ($columns > $bsMaxGridSize) {
		$columns = $bsMaxGridSize;
	}

	$shortCodes = array_chunk($shortCodes, round($shortCodesCount / $columns), true);
	$bsColSize = round($bsMaxGridSize / $columns);
}
?>

<?php if ($shortCodesCount): ?>
	<div class="row shortcodes">
		<?php for ($i = 0; $i < $columns; $i++) : ?>
			<div class="col-sm-<?= $bsColSize ?>">
				<div class="table-responsive">
					<table class="table table-bordered table-condensed">
						<thead class="thead hidden-xs">
						<tr>
							<th><?= Yii::t('backend', 'Short Code') ?></th>
							<th><?= Yii::t('label', 'Description') ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($shortCodes[$i] as $shortCode => $description) : ?>
							<tr>
								<th class="col-autowidth">
									<?= Html::button($shortCode, [
										'class' => 'btn btn-sm btn-block btn-outline grey-mint sbold',
										'type' => 'button',
										'title' => Yii::t('backend', 'Insert Short Code'),
										'data' => [
											'toggle' => 'tooltip',
											'insert-target' => $insertTarget,
										],
									]) ?>
								</th>
								<td><?= $description ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endfor; ?>
	</div>
<?php endif; ?>
