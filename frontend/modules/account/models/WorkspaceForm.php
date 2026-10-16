<?php

namespace frontend\modules\account\models;

use common\models\Feature;
use common\models\Subscriber;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use common\models\Workspace;
use common\models\WorkspaceHasSubscriptionFeature;
use common\models\WorkspaceHasUser;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class WorkspaceForm extends Workspace
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->status = static::STATUS_ACTIVE;
		$this->type = static::TYPE_GENERAL;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['url'], 'required'],
			[['subscription_id'], 'exist', 'skipOnError' => true, 'targetClass' => Subscription::class, 'targetAttribute' => ['subscription_id' => 'id'], 'filter' => function ($query) {
				// Only the signed-in account's own subscriptions.
				$query->andWhere(['subscriber_id' => Subscriber::find()->select('id')->where(['user_id' => Yii::$app->user->id])]);
			}],
			['url', 'string', 'min' => 3, 'max' => 255],
			['url', 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)*$/i'],
			['url', 'trim'],
			['url', 'filter', 'filter' => function ($value) {
				return mb_strtolower($value);
			}],
			['url', 'unique', 'targetClass' => Workspace::class, 'targetAttribute' => ['url' => 'url'], 'when' => function () {
				return $this->isAttributeChanged('url');
			}],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'subscription_id' => Yii::t('label', 'Subscription'),
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
	 * {@inheritdoc}
	 *
	 * Only what the form renders; status, type and deleted stay server-side.
	 */
	public function safeAttributes()
	{
		return array_values(array_intersect(parent::safeAttributes(), ['subscription_id', 'url']));
	}

	/**
	 * @inheritdoc
	 *
	 * Expose the BINARY(16) FK as a canonical UUID string so the widget (whose option
	 * keys are UUID strings) can match the selected value; beforeSave / UuidActiveQuery
	 * convert back to bytes on the save path.
	 */
	public function afterFind()
	{
		parent::afterFind();

		$this->subscription_id = $this->uuidOf('subscription_id') ?: null;
	}

	/**
	 * Saves the WorkspaceHasUser model.
	 *
	 * @return bool
	 */
	public function saveWorkspaceHasUser()
	{
		try {
			/** @var \common\models\User $user */
			if (!($user = Yii::$app->user->identity)) {
				throw new \Exception();
			}

			$workspaceHasUser = WorkspaceHasUser::findOne([
				'workspace_id' => $this->id,
				'user_id' => $user->id,
			]);
			if (!$workspaceHasUser) {
				$workspaceHasUser = new WorkspaceHasUser();
			}
			$workspaceHasUser->setAttributes($user->getAttributes());
			$workspaceHasUser->workspace_id = $this->id;
			$workspaceHasUser->user_id = $user->id;
			if (!WorkspaceHasUser::find()->where(['user_id' => $user->id, 'default' => WorkspaceHasUser::YES])->exists()) {
				$workspaceHasUser->default = WorkspaceHasUser::YES;
			}
			if (!$workspaceHasUser->save()) {
				$this->addErrors($workspaceHasUser->getErrors());
				throw new \Exception('Cannot save WorkspaceHasUser model.');
			}

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Saves the WorkspaceHasSubscriptionFeature models.
	 *
	 * @return bool
	 */
	public function saveWorkspaceHasSubscriptionFeatures()
	{
		try {
			/** @var SubscriptionFeature[] $subscriptionFeatures */
			$subscriptionFeatures = array_diff_key(
				$this->subscription->getSubscriptionFeatures()->indexBy('id')->all(),
				$this->getWorkspaceHasSubscriptionFeatures()->indexBy('subscription_feature_id')->all()
			);

			if (!empty($subscriptionFeatures)) {
				$attributes = ['workspace_id', 'subscription_feature_id'];
				$rows = [];
				foreach ($subscriptionFeatures as $subscriptionFeature) {
					$rows[] = [
						'workspace_id' => $this->id,
						'subscription_feature_id' => $subscriptionFeature->id,
					];
				}
				if (!Yii::$app->db->createCommand()->batchInsert(WorkspaceHasSubscriptionFeature::tableName(), $attributes, $rows)->execute()) {
					throw new \Exception();
				}
			}

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Checks if the workspaces count has reached the limit for its subscription.
	 *
	 * @return bool
	 */
	public function isWorkspacesLimitReached()
	{
		$subscription = $this->subscription;
		return $subscription->getWorkspaces()->count() >= $subscription->getSubscriptionFeatureByName(Feature::WORKSPACES)->value;
	}

	/**
	 * Save url for current workspace
	 *
	 * @return bool
	 */
	public function saveUrl($url)
	{
		$this->updateHtaccess();
		$dirPath = $this->getDirectoryPath();
		// Update the configuration files
		$filePaths = [
			"{$dirPath}/common/config/main.php",
			"{$dirPath}/api/config/main.php",
			"{$dirPath}/backend/config/main.php",
			"{$dirPath}/frontend/config/main.php",
			"{$dirPath}/console/config/main.php",
		];
		foreach ($filePaths as $filePath) {
			if (is_file($filePath)) {
				file_put_contents($filePath, strtr(file_get_contents($filePath), [
					"'baseUrl' => '/$url'" => "'baseUrl' => '/$this->url'",
				]));
				file_put_contents($filePath, strtr(file_get_contents($filePath), [
					"'baseUrl' => '/$url/api'" => "'baseUrl' => '/$this->url/api'",
				]));
			}
		}
	}

	/**
	 * Saves the model.
	 *
	 * @return bool|\yii\db\ActiveRecord|static
	 */
	public function saveModel()
	{
		$isNewRecord = $this->getIsNewRecord();

		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$isNewRecord) {
				$workspace = static::findOne(['id' => $this->id]);
			}
			if ($isNewRecord && $this->isWorkspacesLimitReached()) {
				$this->addError('subscription_id', Yii::t('common', 'The workspaces limit for this subscription has been reached.'));
				throw new \Exception();
			}
			if ($isNewRecord || !$this->code) {
				$this->code = static::generateUniqueCode();
			}
			if (!$this->save()) {
				throw new \Exception();
			}
			if (!$this->saveWorkspaceHasUser()) {
				throw new \Exception();
			}
			if (!$this->saveWorkspaceHasSubscriptionFeatures()) {
				throw new \Exception();
			}
			if ($isNewRecord && !$this->install()) {
				throw new \Exception();
			}
			if ($isNewRecord) {
				if (!$this->install()) {
					throw new \Exception();
				}
			} else {
				if ($workspace->url != $this->url) {
					$this->saveUrl($workspace->url);
				}
			}
			$dbTransaction->commit();
			return $this;
		} catch (\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
