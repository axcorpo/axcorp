<?php

namespace backend\models;

use common\models\Feature;
use common\models\SubscriptionFeature;
use common\models\Workspace;
use common\models\WorkspaceHasSubscriptionFeature;
use common\models\WorkspaceHasUser;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Shared workspace create/update form logic, used by both the Workspaces module and
 * the Subscribers module forms (which were near-identical copies before).
 */
class WorkspaceForm extends Workspace
{
	/**
	 * @var int The Subscriber model ID.
	 */
	public $subscriber_id;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		$this->status = static::STATUS_ACTIVE;
		$this->type = static::TYPE_GENERAL;

		// Prefill the code so the create form starts from a valid unique value; the admin
		// may overwrite it there. Existing records get their real code populated after find.
		// generateUniqueCode() may start with a digit, which the code rule (a MySQL
		// identifier) rejects — regenerate until the prefill passes its own validation.
		if ($this->getIsNewRecord() && !$this->code) {
			do {
				$this->code = static::generateUniqueCode();
			} while (!preg_match('/^[a-zA-Z]/', $this->code));
		}
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), [
			[['subscriber_id', 'domain'], 'required'],
			// The type selects the vertical taxonomy seeds (workspace/install/db/<type>/)
			// imported into the tenant database at install/reinstall.
			['type', 'required'],
			['type', 'in', 'range' => array_keys(static::getTypeLabels())],
			['url', 'string', 'min' => 3, 'max' => 255],
			['url', 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)*$/i'],
			['url', 'trim'],
			['url', 'filter', 'filter' => function ($value) {
				return mb_strtolower($value);
			}],
			['url', 'unique', 'targetClass' => Workspace::class, 'targetAttribute' => ['url' => 'url'], 'when' => function () {
				return $this->isAttributeChanged('url');
			}],
			['domain', 'trim'],
			['domain', 'filter', 'filter' => function ($value) {
				return mb_strtolower($value);
			}],
			['domain', 'unique', 'targetClass' => Workspace::class, 'targetAttribute' => ['domain' => 'domain'], 'when' => function () {
				return $this->isAttributeChanged('domain');
			}],
			// The code doubles as the tenant database name, so it must be a valid MySQL
			// identifier. Editable only at creation; updates keep the stored value.
			['code', 'trim'],
			['code', 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]{2,63}$/', 'message' => Yii::t('common', 'The code must start with a letter and may only contain letters, digits and underscores (3-64 characters).')],
			['code', 'unique', 'targetClass' => Workspace::class, 'targetAttribute' => ['code' => 'code'], 'when' => function () {
				return $this->isAttributeChanged('code');
			}],
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'subscriber_id' => Yii::t('label', 'Subscriber'),
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
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();

		// Expose the ids as canonical UUID strings so the form dropdowns (whose option
		// keys are UUID strings) can match the selected values; beforeSave converts back.
		$this->subscriber_id = $this->subscription ? $this->subscription->uuidOf('subscriber_id') : null;
		$this->subscription_id = $this->uuidOf('subscription_id');
	}

	/**
	 * Saves the WorkspaceHasUser model.
	 *
	 * @return bool
	 */
	public function saveWorkspaceHasUser()
	{
		try {
			if (!($user = $this->subscription->subscriber->user)) {
				throw new \Exception('The subscription has no subscriber user.');
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
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code], __METHOD__);
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
					throw new \Exception('Cannot insert the workspace subscription features.');
				}
			}

			return true;
		} catch (\Exception $e) {
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code], __METHOD__);
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
	 * @return bool|\yii\db\ActiveRecord
	 */
	public function saveModel()
	{
		$isNewRecord = $this->getIsNewRecord();

		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$isNewRecord) {
				$workspace = static::findOne(['id' => $this->id]);
			}
//			if ($isNewRecord && $this->isWorkspacesLimitReached()) {
//				$this->addError('subscription_id', Yii::t('common', 'The workspaces limit for this subscription has been reached.'));
//				throw new \Exception();
//			}
			if (!$isNewRecord) {
				// The code names the tenant database and directory config — never let an
				// update change it, whatever was posted.
				$this->code = $workspace->code;
			} elseif (!$this->code) {
				$this->code = static::generateUniqueCode();
			}
			if (!$this->save()) {
				throw new \Exception('Cannot save the workspace: ' . print_r($this->errors, true));
			}
			if (!$this->saveWorkspaceHasUser()) {
				throw new \Exception('Cannot save the workspace user.');
			}
			if (!$this->saveWorkspaceHasSubscriptionFeatures()) {
				throw new \Exception('Cannot save the workspace subscription features.');
			}
			if ($isNewRecord) {
				if (!$this->install()) {
					throw new \Exception('Cannot install the workspace.');
				}
			} else {
				if ($workspace->url != $this->url) {
					$this->saveUrl($workspace->url);
				}
			}
			$dbTransaction->commit();
			return $this;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			Yii::error(['message' => $e->getMessage(), 'workspace' => $this->code], __METHOD__);
			return false;
		}
	}
}
