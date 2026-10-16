<?php

namespace backend\modules\setting\models;

use common\models\Setting;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;

class ScriptSettingForm extends Setting
{
    /**
     * @var string The scripts in headContent.
     */
    public $headContent;

    /**
     * @var string The scripts in footerContent.
     */
    public $footerContent;
        
	

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['headContent', 'footerContent'], 'string'],
			[['headContent', 'footerContent'], 'trim'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'headContent' => Yii::t('label', 'Header'),
			'footerContent' => Yii::t('label', 'Footer'),
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
	public function afterFind()
	{
		parent::afterFind();

		$this->setAttributes($this->getUnserializedValue('setting'));
	}

	/**
	 * Saves the setting.
	 *
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
        $dbTransaction = static::getDb()->beginTransaction();
        try {
            $this->name = 'script';
            $this->type = static::TYPE_APP;
            $this->status = static::STATUS_ACTIVE;
            $this->setSerializedValue('setting', [
                'headContent' => $this->headContent,
                'footerContent' => $this->footerContent,
            ]);
            if (!$this->save()) {
                throw new \Exception();
            }
            $dbTransaction->commit();
            return $this;
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            return null;
        }
	}
}
