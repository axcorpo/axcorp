<?php

namespace common\behaviors;

use common\models\DocumentSeries;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\db\BaseActiveRecord;

/**
 * DocumentNumberBehavior automatically sets a document series and number properties to the owner class.
 *
 * @property BaseActiveRecord $owner owner ActiveRecord instance.
 *
 * @author Tree Web Solutions Team <treewebsolutions.com@gmail.com>
 */
class DocumentSeriesAndNumberBehavior extends Behavior
{
	/**
	 * @var string The document series attribute of the model.
	 */
	public $documentSeriesModel;

	/**
	 * @var string|\Closure The document series default value.
	 */
	public $documentSeriesDefaultValue;

	/**
	 * @var string The document series attribute of the model.
	 */
	public $documentSeriesAttribute = 'document_series';

	/**
	 * @var string The document number attribute of the model.
	 */
	public $documentNumberAttribute = 'document_number';

	/**
	 * @var string The document status of the model.
	 */
	public $documentStatusAttribute = 'status';

	/**
	 * @var bool Flag that indicates if the owner attributes should be overwritten if already gave values set.
	 */
	public $overwrite = false;

	/**
	 * @inheritdoc
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		parent::init();

		if (empty($this->documentSeriesModel)) {
			throw new InvalidConfigException('The "documentSeriesModel" property must be set to an ActiveRecord class.');
		}
		if (empty($this->documentSeriesDefaultValue)) {
			throw new InvalidConfigException('The "documentSeriesDefaultValue" property must be set to a non-empty string or to a \Closure.');
		}
		if (empty($this->documentSeriesAttribute) || empty($this->documentNumberAttribute)) {
			throw new InvalidConfigException('The "documentSeriesAttribute" and "documentNumberAttribute" properties must be set to a non-empty string.');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
			BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
		];
	}

	/**
	 * Gets the default document series model.
	 *
	 * @return string|null
	 */
	public function getDefaultDocumentSeries()
	{
		if ($this->documentSeriesDefaultValue instanceof \Closure) {
			return call_user_func($this->documentSeriesDefaultValue);
		}
		return $this->documentSeriesDefaultValue;
	}

	/**
	 * Gets the next document number based on the document series.
	 *
	 * @param mixed $documentSeries
	 * @return int
	 */
	public function getNextDocumentNumber($documentSeries = null, $status = null)
	{
		/** @var DocumentSeries $documentSeriesModel */
		$documentSeriesModel = $this->documentSeriesModel;
		$documentSeriesQuery = $documentSeriesModel::find()
			->andWhere([
				'status' => $documentSeriesModel::STATUS_ACTIVE,
				'deleted' => $documentSeriesModel::NO,
			])
			->limit(1);

		if ($documentSeries === null) {
			// Get the first DocumentSeries model
			$documentSeriesModel = $documentSeriesQuery->one();
			$firstNumber = $documentSeriesModel->first_number ?: 1;
			$documentSeries = $documentSeriesModel->name;
		} else {
			// Get the DocumentSeries model by name
			$documentSeriesModel = $documentSeriesQuery->andWhere(['name' => $documentSeries])->one();
			$firstNumber = $documentSeriesModel->first_number ?: 1;
		}

		$maxNumber = $this->owner->find()
			->where([$this->documentSeriesAttribute => $documentSeries])
			->andFilterWhere([$this->documentStatusAttribute => $status])
			->max($this->documentNumberAttribute);

		if (empty($maxNumber) || ($firstNumber > $maxNumber)) {
			$maxNumber = $firstNumber - 1;
		}

		return $maxNumber + 1;
	}

	/**
	 * Sets the document series and number attributes of the owner model.
	 *
	 * @param ModelEvent $event
	 */
	public function beforeSave($event)
	{
		$owner = $this->owner;
		$documentSeriesAttribute = $this->documentSeriesAttribute;
		$documentNumberAttribute = $this->documentNumberAttribute;
		$documentStatusAttribute = $this->documentStatusAttribute;

		if ($owner->getIsNewRecord() || $this->overwrite === true) {
			if (empty($owner->$documentSeriesAttribute) || $this->overwrite === true) {
				$owner->$documentSeriesAttribute = $this->getDefaultDocumentSeries();
			}
			if (empty($owner->$documentNumberAttribute) || $this->overwrite === true) {
				$owner->$documentNumberAttribute = $this->getNextDocumentNumber($owner->$documentSeriesAttribute, $owner->$documentStatusAttribute);
			}
		}
	}
}
