<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%invoice_has_template}}".
 *
 * @property int $invoice_id
 * @property int $template_id
 *
 * @property Invoice $invoice
 * @property Template $template
 */
class InvoiceHasTemplate extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%invoice_has_template}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['invoice_id', 'template_id'], 'required'],
			[['invoice_id', 'template_id'], 'integer'],
			[['invoice_id', 'template_id'], 'unique', 'targetAttribute' => ['invoice_id', 'template_id']],
			[['invoice_id'], 'exist', 'skipOnError' => true, 'targetClass' => Invoice::class, 'targetAttribute' => ['invoice_id' => 'id']],
			[['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => Template::class, 'targetAttribute' => ['template_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'invoice_id' => Yii::t('label', 'Invoice ID'),
			'template_id' => Yii::t('label', 'Template ID'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getInvoice()
	{
		return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTemplate()
	{
		return $this->hasOne(Template::class, ['id' => 'template_id']);
	}
}
