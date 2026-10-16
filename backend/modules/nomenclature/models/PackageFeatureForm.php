<?php

namespace backend\modules\nomenclature\models;

use common\models\Feature;
use common\models\PackageFeature;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Adds or edits one feature of a package.
 *
 * The feature is chosen from the fixed set declared on [[Feature]] (workspaces, users)
 * and stored by name in package_feature; no row of the `feature` nomenclature is
 * involved, so feature_id stays null.
 */
class PackageFeatureForm extends PackageFeature
{
	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['package_id', 'name', 'value'], 'required'],
			[['name'], 'in', 'range' => array_keys(Feature::getFeatureLabels())],
			[['name'], 'unique', 'targetAttribute' => ['package_id', 'name'], 'filter' => ['deleted' => PackageFeature::NO], 'message' => Yii::t('common', 'This feature is already assigned to the package.'), 'when' => function () {
				return $this->isAttributeChanged('name') || $this->isAttributeChanged('package_id');
			}],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'name' => Yii::t('common', 'Feature'),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * @inheritdoc
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		$this->feature_id = null;
		$this->renewable = PackageFeature::NO;

		return parent::save($runValidation, $attributeNames);
	}
}
