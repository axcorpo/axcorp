<?php

namespace common\widgets\datatable;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\JsExpression;

class ImageColumn extends BaseDataTableColumn
{
	/**
	 * @inheritdoc
	 */
	public $className = 'image-column col-autowidth text-center';

	/**
	 * @inheritdoc
	 */
	public $searchable = false;

	/**
	 * @inheritdoc
	 */
	public $orderable = false;

	/**
	 * @var array Image configuration.
	 */
	public $config = [];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		// Set configuration defaults
		$this->config = ArrayHelper::merge([
			'altAttribute' => 'title',
			'gallery' => false,
			'options' => [
				'class' => 'img-responsive',
				'src' => null,
				'alt' => null,
			],
		], $this->config);
		// Create a custom renderer
		$this->render = $this->buildRender();
	}

	/**
	 * Builds a custom renderer.
	 *
	 * @return string
	 */
	protected function buildRender()
	{
		$tag = Html::img('__SRC__', $this->config['options']);

		if ($this->config['gallery'] !== false) {
			$tag = Html::a($tag, '__HREF__', [
				'data' => [
					'fancybox' => $this->config['gallery'],
					'caption' => '__ALT__',
					'options' => $this->config['galleryOptions'],
				],
			]);
		}

		return new JsExpression('function (data, type, row, meta) {
			var content = ' . json_encode($tag) . ';
			
			content = content.replace("__HREF__", ' . ($this->config['options']['src'] ? '"' . $this->config['options']['src'] . '"' : 'data') . ');
			content = content.replace("__SRC__", ' . ($this->config['options']['src'] ? '"' . $this->config['options']['src'] . '"' : 'data') . ');
			content = content.replace("__ALT__", ' . ($this->config['options']['alt'] ? '"' . $this->config['options']['alt'] . '"' : 'row["' . $this->config['altAttribute'] . '"]') . ');

			return content;
		}');
	}
}