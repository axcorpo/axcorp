<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%support_ticket_department_translation}}".
 *
 * @property int $support_ticket_department_id
 * @property string $language_id
 * @property string $name
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property SupportTicketDepartment $supportTicketDepartment
 */
class SupportTicketDepartmentTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%support_ticket_department_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['support_ticket_department_id', 'language_id', 'name'], 'required'],
			[['support_ticket_department_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['support_ticket_department_id', 'language_id'], 'unique', 'targetAttribute' => ['support_ticket_department_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['support_ticket_department_id'], 'exist', 'skipOnError' => true, 'targetClass' => SupportTicketDepartment::class, 'targetAttribute' => ['support_ticket_department_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'support_ticket_department_id' => Yii::t('label', 'Support Ticket Department ID'),
			'language_id' => Yii::t('label', 'Language ID'),
			'name' => Yii::t('label', 'Name'),
			'content' => Yii::t('label', 'Content'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguage()
	{
		return $this->hasOne(Language::class, ['language_id' => 'language_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSupportTicketDepartment()
	{
		return $this->hasOne(SupportTicketDepartment::class, ['id' => 'support_ticket_department_id']);
	}
}
