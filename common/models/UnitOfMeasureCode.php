<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%unit_of_measure_code}}".
 *
 * @property int $id
 * @property string $code
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property UnitOfMeasure[] $unitOfMeasures
 * @property UnitOfMeasureCodeTranslation[] $unitOfMeasureCodeTranslations
 * @property UnitOfMeasureCodeTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class UnitOfMeasureCode extends UuidActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%unit_of_measure_code}}';
    }

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function behaviors()
	{
		return [
			[
				'class' => BlameableBehavior::class,
			],
			[
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
		];
	}

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code', 'status'], 'required'],
            [['created_by', 'updated_by', 'status', 'deleted'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['code'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('label', 'ID'),
            'code' => Yii::t('label', 'Code'),
            'created_by' => Yii::t('label', 'Created By'),
            'updated_by' => Yii::t('label', 'Updated By'),
            'created_at' => Yii::t('label', 'Created At'),
            'updated_at' => Yii::t('label', 'Updated At'),
            'status' => Yii::t('label', 'Status'),
            'deleted' => Yii::t('label', 'Deleted'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUnitOfMeasures()
    {
        return $this->hasMany(UnitOfMeasure::class, ['code_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUnitOfMeasureCodeTranslations()
    {
        return $this->hasMany(UnitOfMeasureCodeTranslation::class, ['code_id' => 'id']);
    }

	/**
	 * Gets the model translation.
	 *
	 * @param string|null $language
	 * @return mixed
	 */
	public function getTranslation($language = null)
	{
		$language = $language ?: Yii::$app->language;

		return ArrayHelper::index($this->unitOfMeasureCodeTranslations, 'language_id')[$language];
	}

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLanguages()
    {
        return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%unit_of_measure_code_translation}}', ['code_id' => 'id']);
    }

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getUpdater()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

	/**
	 * Finds all active records.
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public static function findAllOfMeasureCodes()
	{
		return static::getDb()->cache(function ($db) {
			return static::find()
				->alias('uomc')
				->joinWith([
					'unitOfMeasureCodeTranslations uomct' => function (ActiveQuery $query) {
						$query->andOnCondition(['uomct.language_id' => Yii::$app->language]);
					},
				])
				->where([
					'uomc.status' => self::STATUS_ACTIVE,
					'uomc.deleted' => self::NO,
				])
				->orderBy(['uomc.id' => SORT_ASC])
				->indexBy('id')
				->all();
		}, 0, new TagDependency(['tags' => __FUNCTION__]));
	}
}
