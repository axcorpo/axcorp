<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * Workspace Database Update form
 */
class WorkspaceDatabaseUpdateForm extends Workspace
{
	public $query;

	/**
	 * @var string|null When set, the query is executed only against the workspace
	 * with this code (used to rerun a single database). When empty, the query runs
	 * against every active workspace.
	 *
	 * NOTE: This must NOT be named `code` because `code` is a column of the parent
	 * {@see Workspace} model; a public property of the same name would shadow the
	 * ActiveRecord attribute and break $model->getWorkspaceDb().
	 */
	public $workspaceCode;

	/**
	 * @var WorkspaceDatabaseUpdateLog[] The per-workspace results of the last run.
	 */
	public $results = [];

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['query'], 'required'],
			[['workspaceCode'], 'string', 'max' => 255],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), [
			'query' => Yii::t('label', 'Query'),
		]);
	}

	/**
	 * Executes the query against the targeted workspace databases and records the
	 * outcome (success or the exact error) for each one.
	 *
	 * @return WorkspaceDatabaseUpdateLog[] The per-workspace result log entries.
	 */
	public function saveModel()
	{
		$query = static::find()
			->where([
				'status' => static::STATUS_ACTIVE,
				'deleted' => static::NO,
			]);
		if (!empty($this->workspaceCode)) {
			$query->andWhere(['code' => $this->workspaceCode]);
		}
		$models = $query->all();

		$this->query = html_entity_decode($this->query);
		$this->results = [];

		if (empty($models)) {
			$this->addError('workspaceCode', Yii::t('common', 'No active workspace matches the given code.'));
			return $this->results;
		}

		$batchId = uniqid('', false);
		foreach ($models as $model) {
			$log = new WorkspaceDatabaseUpdateLog();
			$log->batch_id = $batchId;
			$log->workspace_id = $model->id;
			$log->workspace_code = $model->code;
			$log->workspace_url = $model->url;
			$log->query = $this->query;

			try {
				$connection = $model->getWorkspaceDb();
				$command = $connection->createCommand()->setRawSql($this->query);
				$command->execute();
				$log->status = WorkspaceDatabaseUpdateLog::RESULT_SUCCESS;
			} catch (\Exception $e) {
				$log->status = WorkspaceDatabaseUpdateLog::RESULT_ERROR;
				$log->error = $e->getMessage();
			}

			$log->save(false);
			$this->results[] = $log;
		}

		return $this->results;
	}
}
