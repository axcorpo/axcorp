<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "{{%package_translation}}".
 *
 * @property int $package_id
 * @property string $language_id
 * @property string $name
 * @property string $content
 * @property int $deleted
 *
 * @property Language $language
 * @property Package $package
 */
class PackageTranslation extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%package_translation}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['package_id', 'language_id', 'name'], 'required'],
			[['package_id', 'deleted'], 'integer'],
			[['content'], 'string'],
			[['language_id'], 'string', 'max' => 5],
			[['name'], 'string', 'max' => 255],
			[['package_id', 'language_id'], 'unique', 'targetAttribute' => ['package_id', 'language_id']],
			[['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'language_id']],
			[['package_id'], 'exist', 'skipOnError' => true, 'targetClass' => Package::class, 'targetAttribute' => ['package_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'package_id' => Yii::t('label', 'Package ID'),
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
	public function getPackage()
	{
		return $this->hasOne(Package::class, ['id' => 'package_id']);
	}
}
