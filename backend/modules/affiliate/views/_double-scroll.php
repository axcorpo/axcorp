<?php

/* @var $this yii\web\View */
/* @var $id string the DataTable id */

use yii\helpers\Json;

// The top scrollbar of the wide lists, wired like subscriber/invoice/index.php (doubleScroll
// from backend/web/js/main-app.js) but sized per table: a page can hold several lists, and
// the plugin puts this table's bar right before its wrapper. A collapsed portlet is
// measured again when it opens.
$this->registerJs('
	(function () {
		var $table = $("#" + ' . Json::htmlEncode($id) . ');
		var resize = function () {
			$table.parent().doubleScroll();
			$table.parent().prev(".doubleScroll-scroll-wrapper").find(".doubleScroll-scroll").width($table.outerWidth());
		};
		$table.on("draw.dt", resize);
		$table.on("column-visibility.dt", resize);
		$(window).resize(resize);
		$table.closest(".portlet").on("click", ".portlet-title .tools .expand", function () {
			setTimeout(resize, 400);
		});
	})();
');
