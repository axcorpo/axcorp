<?php

namespace common\models;

use tws\behaviors\DateTimeBehavior;
use Yii;

/**
 * This is the model class for table "{{%workspace_has_subscription_feature}}".
 *
 * @property int $workspace_id
 * @property int $subscription_feature_id
 * @property int $quota
 * @property string $renewed_at
 *
 * @property SubscriptionFeature $subscriptionFeature
 * @property Workspace $workspace
 */
class WorkspaceHasSubscriptionFeature extends \yii\db\ActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%workspace_has_subscription_feature}}';
	}

	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'DateTimeBehavior' => [
				'class' => DateTimeBehavior::class,
				'attributes' => ['renewed_at'],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['workspace_id', 'subscription_feature_id'], 'required'],
			[['workspace_id', 'subscription_feature_id', 'quota'], 'integer'],
			[['renewed_at'], 'safe'],
			[['renewed_at'], 'default'],
			[['workspace_id', 'subscription_feature_id'], 'unique', 'targetAttribute' => ['workspace_id', 'subscription_feature_id']],
			[['subscription_feature_id'], 'exist', 'skipOnError' => true, 'targetClass' => SubscriptionFeature::class, 'targetAttribute' => ['subscription_feature_id' => 'id']],
			[['workspace_id'], 'exist', 'skipOnError' => true, 'targetClass' => Workspace::class, 'targetAttribute' => ['workspace_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'workspace_id' => Yii::t('label', 'Workspace ID'),
			'subscription_feature_id' => Yii::t('label', 'Subscription Feature ID'),
			'quota' => Yii::t('label', 'Quota'),
			'renewed_at' => Yii::t('label', 'Renewed At'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getSubscriptionFeature()
	{
		return $this->hasOne(SubscriptionFeature::class, ['id' => 'subscription_feature_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getWorkspace()
	{
		return $this->hasOne(Workspace::class, ['id' => 'workspace_id']);
	}
}
