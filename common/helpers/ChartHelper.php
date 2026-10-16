<?php

namespace common\helpers;

use common\models\AffiliateCommission;
use InvalidArgumentException;
use tws\widgets\chart\Chart;
use tws\widgets\chart\ChartJsAsset;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\web\View;

/**
 * The shared look of the Chart.js charts (frontend and backend): the validated palette and
 * the Chart.js config builders, so every chart follows the same rules.
 *
 * The bundled Chart.js is v2.9 (tws\widgets\chart\ChartJsAsset), so the options are the
 * v2 ones (`tooltips`, `scales.yAxes[]`, `horizontalBar`). v2 has no bar `borderRadius`:
 * [[widget()]] registers two bar types, `roundedBar` and `roundedHorizontalBar`, that round
 * the data end of each bar (only the top segment of a stack) and leave a surface-coloured
 * gap between stacked segments.
 *
 * Rules the builders enforce: one value axis starting at 0, integer ticks for counts,
 * money through Intl.NumberFormat in ONE currency per chart, a legend only for 2+ series,
 * index tooltips with formatted values, recessive grid on the value axis only.
 *
 * Usage:
 * ```php
 * $config = ChartHelper::bar($monthLabels, [
 *     ['label' => 'Paid', 'data' => [...], 'color' => ChartHelper::statusColor(AffiliateCommission::STATUS_PAID)],
 * ], ['stacked' => true, 'format' => ChartHelper::FORMAT_CURRENCY, 'currency' => 'RON', 'titles' => $monthNames]);
 * echo ChartHelper::widget($config, ['aria-label' => 'Commissions per month']);
 * ```
 * Pair every chart with a table of the same numbers (the aqua slot is below 3:1 contrast).
 */
class ChartHelper
{
	// Categorical slots, validated for colour-blind separation. Fixed order, never cycled:
	// a 4th series folds into "Other" instead of getting a generated colour.
	const BLUE = '#2a78d6';
	const ORANGE = '#eb6834';
	const AQUA = '#1baf7a';

	// Chrome: text never takes a series colour.
	const GRID = '#e1e0d9';
	const AXIS = '#898781';
	const TEXT = '#52514e';
	const TEXT_STRONG = '#0b0b0b';
	const SURFACE = '#fcfcfb';

	const FORMAT_INTEGER = 'integer';
	const FORMAT_CURRENCY = 'currency';

	const BAR_RADIUS = 4;
	const BAR_GAP = 2;
	const BAR_MAX_THICKNESS = 28;

	/**
	 * The categorical palette, slot 1 first.
	 *
	 * @return string[]
	 */
	public static function colors()
	{
		return [self::BLUE, self::ORANGE, self::AQUA];
	}

	/**
	 * The colour of a categorical slot (1-based).
	 *
	 * @param int $slot
	 * @return string
	 * @throws InvalidArgumentException past the last slot — fold the extra series instead
	 */
	public static function color($slot)
	{
		$colors = static::colors();
		if (!isset($colors[$slot - 1])) {
			throw new InvalidArgumentException("There is no chart colour slot {$slot}.");
		}
		return $colors[$slot - 1];
	}

	/**
	 * The fixed colour of a commission status, whatever its rank in a chart. Cancelled
	 * commissions are never charted, so they have none.
	 *
	 * @param int $status AffiliateCommission::STATUS_*
	 * @return string|null
	 */
	public static function statusColor($status)
	{
		$map = [
			AffiliateCommission::STATUS_PAID => self::BLUE,
			AffiliateCommission::STATUS_PAYABLE => self::ORANGE,
			AffiliateCommission::STATUS_PENDING => self::AQUA,
		];
		return $map[(int) $status] ?? null;
	}

	/**
	 * The Chart.js options every chart starts from. The overrides are merged with
	 * ArrayHelper::merge() (lists are appended: use ReplaceArrayValue to replace one).
	 *
	 * @param array $overrides
	 * @return array
	 */
	public static function baseOptions(array $overrides = [])
	{
		return ArrayHelper::merge([
			'responsive' => true,
			// The height comes from the container (see widget()), the width from the layout.
			'maintainAspectRatio' => false,
			'animation' => ['duration' => 300],
			'layout' => ['padding' => ['top' => 4, 'right' => 8]],
			'title' => ['display' => false],
			'legend' => [
				'display' => false,
				'position' => 'top',
				'align' => 'start',
				'labels' => ['fontColor' => self::TEXT, 'boxWidth' => 12, 'padding' => 16],
			],
			'tooltips' => ['mode' => 'index', 'intersect' => false],
			'hover' => ['mode' => 'index', 'intersect' => false],
		], $overrides);
	}

	/**
	 * A line chart config, one 2px line per series.
	 *
	 * @param string[] $labels category (x) labels
	 * @param array $series `[['label' => string, 'data' => number[], 'color' => string], ...]`
	 * @param array $settings see [[buildConfig()]]
	 * @return array Chart.js config
	 */
	public static function line(array $labels, array $series, array $settings = [])
	{
		$datasets = [];
		foreach ($series as $item) {
			$datasets[] = [
				'label' => $item['label'],
				'data' => array_values($item['data']),
				'fill' => false,
				'lineTension' => 0,
				'borderWidth' => 2,
				'borderColor' => $item['color'],
				'backgroundColor' => $item['color'],
				'pointRadius' => 4,
				'pointHoverRadius' => 6,
				'pointHitRadius' => 8,
				'pointBackgroundColor' => $item['color'],
				'pointBorderColor' => $item['color'],
				'pointHoverBackgroundColor' => $item['color'],
				'pointHoverBorderColor' => $item['color'],
			];
		}
		return static::buildConfig('line', $labels, $datasets, $settings);
	}

	/**
	 * A bar chart config: thin bars, rounded data end, stacked segments split by a gap.
	 *
	 * @param string[] $labels category labels
	 * @param array $series `[['label' => string, 'data' => number[], 'color' => string], ...]`
	 * @param array $settings see [[buildConfig()]], plus `stacked` and `horizontal` (bool)
	 * @return array Chart.js config
	 */
	public static function bar(array $labels, array $series, array $settings = [])
	{
		$datasets = [];
		foreach ($series as $item) {
			$datasets[] = [
				'label' => $item['label'],
				'data' => array_values($item['data']),
				'backgroundColor' => $item['color'],
				'hoverBackgroundColor' => $item['color'],
				'borderWidth' => 0,
				'maxBarThickness' => self::BAR_MAX_THICKNESS,
				// Read by the rounded bar element registered in widget().
				'borderRadius' => self::BAR_RADIUS,
				'stackGap' => self::BAR_GAP,
			];
		}
		$type = empty($settings['horizontal']) ? 'roundedBar' : 'roundedHorizontalBar';
		return static::buildConfig($type, $labels, $datasets, $settings);
	}

	/**
	 * Whether any series has a non-zero value — charts without one show a message instead.
	 *
	 * @param array $series as for [[line()]] / [[bar()]], or plain number lists
	 * @return bool
	 */
	public static function hasData(array $series)
	{
		foreach ($series as $item) {
			foreach ((isset($item['data']) ? $item['data'] : $item) as $value) {
				if ((float) $value != 0) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Renders a chart config in a fixed-height container through the Chart widget.
	 *
	 * @param array $config from [[line()]] / [[bar()]]
	 * @param array $canvasOptions HTML attributes of the canvas (give it an `aria-label`)
	 * @param int $height container height in px
	 * @return string HTML
	 */
	public static function widget(array $config, array $canvasOptions = [], $height = 280)
	{
		$view = Yii::$app->getView();
		ChartJsAsset::register($view);
		// POS_END inline code runs after the Chart.js file and before the widgets' ready blocks.
		$view->registerJs(static::extensionJs(), View::POS_END, 'chart-helper-extension');

		$canvasOptions = array_merge(['role' => 'img'], $canvasOptions);
		$canvas = Chart::widget([
			'type' => Chart::TYPE_CHARTJS,
			'options' => $canvasOptions,
			'clientOptions' => $config,
		]);
		return Html::tag('div', $canvas, ['class' => 'position-relative', 'style' => ['height' => (int) $height . 'px']]);
	}

	/**
	 * Assembles a config.
	 *
	 * Settings:
	 * - `format`: FORMAT_INTEGER (default) or FORMAT_CURRENCY
	 * - `currency`: ISO code, required with FORMAT_CURRENCY — one currency per chart
	 * - `locale`: number/currency locale, default the application language
	 * - `titles`: tooltip titles per label (e.g. full month names), default the labels
	 * - `totalLabel`: when set on a stacked chart, the tooltip footer shows the total
	 * - `links`: a URL (or null) per label — clicking that category opens it
	 * - `allLabels`: never skip a category label (ranked bars: every name must show)
	 * - `labelLength`: `[narrow, wide]` characters a horizontal bar label keeps, default [14, 24]
	 * - `options`: raw Chart.js options merged last (see [[baseOptions()]])
	 *
	 * @param string $type
	 * @param string[] $labels
	 * @param array $datasets
	 * @param array $settings
	 * @return array
	 */
	protected static function buildConfig($type, array $labels, array $datasets, array $settings)
	{
		$format = $settings['format'] ?? self::FORMAT_INTEGER;
		$currency = $settings['currency'] ?? null;
		if ($format === self::FORMAT_CURRENCY && !$currency) {
			throw new InvalidArgumentException('A currency chart needs its currency.');
		}
		$locale = $settings['locale'] ?? Yii::$app->language;
		$stacked = !empty($settings['stacked']);
		$horizontal = $type === 'roundedHorizontalBar';

		$valueAxis = [
			'stacked' => $stacked,
			'gridLines' => ['color' => self::GRID, 'zeroLineColor' => self::GRID, 'drawBorder' => false, 'drawTicks' => false],
			'ticks' => [
				'beginAtZero' => true,
				'fontColor' => self::AXIS,
				'padding' => 8,
				'maxTicksLimit' => 6,
				'callback' => new JsExpression('function (value) { return ' . static::numberFormatJs($format, $currency, $locale, true) . '.format(value); }'),
			],
		];
		if ($format === self::FORMAT_INTEGER) {
			$valueAxis['ticks']['precision'] = 0;
		}
		$categoryAxis = [
			'stacked' => $stacked,
			'gridLines' => ['display' => false, 'drawBorder' => false],
			'ticks' => ['fontColor' => self::AXIS, 'maxRotation' => 0, 'autoSkipPadding' => 20],
		];
		if ($horizontal && $format === self::FORMAT_CURRENCY) {
			// Amount ticks ("20.000 LEI") are wide: fewer of them, never rotated.
			$valueAxis['ticks']['maxTicksLimit'] = 4;
			$valueAxis['ticks']['maxRotation'] = 0;
		}
		if (!empty($settings['allLabels'])) {
			$categoryAxis['ticks']['autoSkip'] = false;
		}
		if ($horizontal) {
			list($narrow, $wide) = $settings['labelLength'] ?? [14, 24];
			// Long names (affiliates) are cut on the axis (shorter on phones, where Chart.js
			// would otherwise clip them); the tooltip shows them whole.
			// A label given as a list of lines (e.g. a name and its count) is cut line by line.
			$categoryAxis['ticks']['callback'] = new JsExpression('function (label) { var max = this.chart && this.chart.width < 420 ? ' . (int) $narrow . ' : ' . (int) $wide . '; var cut = function (line) { line = String(line); return line.length > max ? line.slice(0, max - 1) + "…" : line; }; return Array.isArray(label) ? label.map(cut) : cut(label); }');
		}

		$value = 'data.datasets[item.datasetIndex].data[item.index]';
		$formatter = static::numberFormatJs($format, $currency, $locale, false);
		$callbacks = [
			'title' => new JsExpression('function (items, data) { var titles = ' . Json::htmlEncode(array_values($settings['titles'] ?? $labels)) . '; return items.length ? titles[items[0].index] : ""; }'),
			'label' => new JsExpression("function (item, data) { return data.datasets[item.datasetIndex].label + ': ' + {$formatter}.format({$value}); }"),
		];
		if ($stacked && count($datasets) > 1 && !empty($settings['totalLabel'])) {
			$callbacks['footer'] = new JsExpression('function (items, data) { var total = 0; items.forEach(function (item) { total += Number(' . $value . ') || 0; }); return ' . Json::htmlEncode(trim($settings['totalLabel'])) . " + ': ' + {$formatter}.format(total); }");
		}

		// Labels go in as escaped JS literals: the widget's Json::encode() leaves "</script>" as is.
		foreach ($datasets as &$dataset) {
			$dataset['label'] = new JsExpression(Json::htmlEncode((string) $dataset['label']));
		}
		unset($dataset);

		$options = static::baseOptions([
			'legend' => ['display' => count($datasets) > 1],
			'tooltips' => [
				'callbacks' => $callbacks,
				// Horizontal bars are picked along y, like Chart.js' own horizontalBar default.
				'axis' => $horizontal ? 'y' : 'x',
			],
			'hover' => ['axis' => $horizontal ? 'y' : 'x'],
			'scales' => [
				'xAxes' => [$horizontal ? $valueAxis : $categoryAxis],
				'yAxes' => [$horizontal ? $categoryAxis : $valueAxis],
			],
		]);
		if (!empty($settings['links'])) {
			// Clicking anywhere on a category (index mode, like the tooltip) opens its link.
			$links = Json::htmlEncode(array_values($settings['links']));
			$link = "var links = {$links}, el = elements && elements[0], link = el ? links[el._index] : null;";
			$options['onClick'] = new JsExpression("function (event, elements) { {$link} if (link) { window.location.href = link; } }");
			$options['onHover'] = new JsExpression("function (event, elements) { {$link} event.target.style.cursor = link ? 'pointer' : 'default'; }");
		}
		if (!empty($settings['options'])) {
			$options = ArrayHelper::merge($options, $settings['options']);
		}

		return [
			'type' => $type,
			'data' => [
				'labels' => new JsExpression(Json::htmlEncode(array_values(array_map(function ($label) {
					// A list of strings is a multi-line label.
					return is_array($label) ? array_values(array_map('strval', $label)) : (string) $label;
				}, $labels)))),
				'datasets' => $datasets,
			],
			'options' => $options,
		];
	}

	/**
	 * A JS `Intl.NumberFormat` constructor call for the format.
	 *
	 * @param string $format
	 * @param string|null $currency
	 * @param string $locale
	 * @param bool $axis axis ticks drop the decimals
	 * @return string JS
	 */
	protected static function numberFormatJs($format, $currency, $locale, $axis)
	{
		if ($format === self::FORMAT_CURRENCY) {
			// The number comes from Intl, the currency word from the app's own formatter, so a
			// chart says "LEI" where the rest of the page does (Intl alone would print "RON").
			$digits = $axis ? 0 : 2;
			$options = ['minimumFractionDigits' => $digits, 'maximumFractionDigits' => $digits];
			return '({format: function (v) { return new Intl.NumberFormat(' . Json::htmlEncode($locale) . ', ' . Json::htmlEncode($options) . ').format(v) + " " + ' . Json::htmlEncode(static::currencyLabel($currency)) . '; }})';
		}
		return 'new Intl.NumberFormat(' . Json::htmlEncode($locale) . ', ' . Json::htmlEncode(['maximumFractionDigits' => 0]) . ')';
	}

	/**
	 * The currency word the application prints after amounts (e.g. "LEI" for RON), taken
	 * from Yii's formatter with the same code mapping ApplicationBootstrap applies.
	 *
	 * @param string $currency ISO code
	 * @return string
	 */
	public static function currencyLabel($currency)
	{
		$code = strtoupper((string) $currency);
		$code = $code === 'RON' ? 'Lei' : $code;
		try {
			$formatted = Yii::$app->formatter->asCurrency(0, $code);
		} catch (\Exception $e) {
			return strtoupper((string) $currency);
		}
		$label = trim(preg_replace('/[\d.,\s\x{00A0}\x{202F}]+/u', ' ', $formatted));
		return $label !== '' ? $label : strtoupper((string) $currency);
	}

	/**
	 * An amount as the charts print it ("1.234,50 LEI"), for the tiles and tables next
	 * to them, so the page and its charts say the same currency word.
	 *
	 * @param float|string $amount
	 * @param string $currency ISO code
	 * @param int $decimals
	 * @return string plain text (encode it in HTML)
	 */
	public static function formatAmount($amount, $currency, $decimals = 2)
	{
		return Yii::$app->formatter->asDecimal((float) $amount, $decimals) . ' ' . static::currencyLabel($currency);
	}

	/**
	 * The JS registering the rounded bar types on Chart.js v2 (once per page).
	 *
	 * @return string
	 */
	protected static function extensionJs()
	{
		$text = Json::htmlEncode(self::TEXT);
		return <<<JS
(function (Chart) {
	if (!Chart || Chart.controllers.roundedBar) {
		return;
	}
	Chart.defaults.global.defaultFontColor = {$text};
	Chart.defaults.global.defaultFontFamily = window.getComputedStyle(document.body).fontFamily || Chart.defaults.global.defaultFontFamily;

	// Where a bar sits in its stack: only the data end is rounded, and a segment resting
	// on another one leaves a gap. Chart.js stacks visible datasets in index order,
	// positives and negatives separately.
	function stackPosition(el, horizontal) {
		var chart = el._chart, datasets = chart.data.datasets, di = el._datasetIndex, i = el._index;
		var meta = chart.getDatasetMeta(di);
		var scale = chart.scales[horizontal ? meta.xAxisID : meta.yAxisID];
		var position = {isEnd: true, hasBelow: false};
		if (!scale || !scale.options.stacked) {
			return position;
		}
		var sign = Number(datasets[di].data[i]) < 0 ? -1 : 1;
		for (var j = 0; j < datasets.length; j++) {
			var v = Number(datasets[j].data[i]) || 0;
			if (j === di || v === 0 || (v < 0 ? -1 : 1) !== sign || !chart.isDatasetVisible(j)) {
				continue;
			}
			if (j < di) {
				position.hasBelow = true;
			} else {
				position.isEnd = false;
			}
		}
		return position;
	}

	var RoundedRectangle = Chart.elements.Rectangle.extend({
		draw: function () {
			var vm = this._view, ctx = this._chart.ctx, ds = this._chart.data.datasets[this._datasetIndex];
			var horizontal = vm.width === undefined, l, r, t, b, end;
			if (horizontal) {
				t = vm.y - vm.height / 2; b = vm.y + vm.height / 2;
				l = Math.min(vm.x, vm.base); r = Math.max(vm.x, vm.base);
				end = vm.x >= vm.base ? 'right' : 'left';
			} else {
				l = vm.x - vm.width / 2; r = vm.x + vm.width / 2;
				t = Math.min(vm.y, vm.base); b = Math.max(vm.y, vm.base);
				end = vm.y <= vm.base ? 'top' : 'bottom';
			}
			var position = stackPosition(this, horizontal), gap = ds.stackGap || 0;
			if (position.hasBelow) {
				if (end === 'top') { b -= gap; } else if (end === 'bottom') { t += gap; } else if (end === 'right') { l += gap; } else { r -= gap; }
			}
			if (r - l <= 0 || b - t <= 0) {
				return;
			}
			var rad = position.isEnd ? Math.max(0, Math.min(ds.borderRadius || 0, (r - l) / 2, (b - t) / 2)) : 0;
			var c = {tl: 0, tr: 0, br: 0, bl: 0};
			if (end === 'top') { c.tl = c.tr = rad; } else if (end === 'bottom') { c.bl = c.br = rad; } else if (end === 'right') { c.tr = c.br = rad; } else { c.tl = c.bl = rad; }
			ctx.save();
			ctx.fillStyle = vm.backgroundColor;
			ctx.beginPath();
			ctx.moveTo(l + c.tl, t);
			ctx.lineTo(r - c.tr, t);
			ctx.arcTo(r, t, r, t + c.tr, c.tr);
			ctx.lineTo(r, b - c.br);
			ctx.arcTo(r, b, r - c.br, b, c.br);
			ctx.lineTo(l + c.bl, b);
			ctx.arcTo(l, b, l, b - c.bl, c.bl);
			ctx.lineTo(l, t + c.tl);
			ctx.arcTo(l, t, l + c.tl, t, c.tl);
			ctx.closePath();
			ctx.fill();
			ctx.restore();
		}
	});

	// v2.9 keeps the bar sizing (categoryPercentage, barPercentage) in per-type dataset defaults.
	var datasetDefaults = Chart.defaults.global.datasets;
	if (datasetDefaults) {
		datasetDefaults.roundedBar = Chart.helpers.clone(datasetDefaults.bar || {});
		datasetDefaults.roundedHorizontalBar = Chart.helpers.clone(datasetDefaults.horizontalBar || datasetDefaults.bar || {});
	}
	Chart.defaults.roundedBar = Chart.helpers.clone(Chart.defaults.bar);
	Chart.controllers.roundedBar = Chart.controllers.bar.extend({dataElementType: RoundedRectangle});
	Chart.defaults.roundedHorizontalBar = Chart.helpers.clone(Chart.defaults.horizontalBar);
	Chart.controllers.roundedHorizontalBar = Chart.controllers.horizontalBar.extend({dataElementType: RoundedRectangle});
})(window.Chart);
JS;
	}
}
