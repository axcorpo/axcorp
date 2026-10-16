<?php

namespace backend\modules\nomenclature\models;

use common\models\Feature;
use common\models\Package;
use common\models\PackageFeature;
use common\models\PackageTranslation;
use common\models\ScheduledTask;
use common\models\Subscription;
use common\models\SubscriptionFeature;
use common\models\Template;
use common\models\Workspace;
use common\models\WorkspaceHasSubscriptionFeature;
use common\models\WsTemplate;
use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

class SubscriptionUpgradeForm extends Subscription
{
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
	public function attributes()
	{
		$attributes = parent::attributes();

		foreach (array_keys(Feature::getFeatureLabels()) as $packageFeatureName) {
			$attributes[] = $packageFeatureName;
		}

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return ArrayHelper::merge(parent::rules(), []);
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return ArrayHelper::merge(parent::attributeLabels(), Feature::getFeatureLabels(), []);
	}

	/**
	 * @inheritdoc
	 */
	public function afterFind()
	{
		parent::afterFind();
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios()
	{
		return Model::scenarios();
	}

	/**
	 * Saves the SubscriptionFeature models.
	 *
	 * @return bool
	 */
	protected function saveSubscriptionFeatures()
	{
		try {
			$availableSubscriptionFeatures = $this->package->getPackageFeatures()->indexBy('name')->all();
			$existingSubscriptionFeatures = $this->getSubscriptionFeatures()->indexBy('name')->all();
			$newSubscriptionFeatures = array_diff(array_keys($availableSubscriptionFeatures), array_keys($existingSubscriptionFeatures));

			$subscriptionFeatures = [];

			if (!empty($newSubscriptionFeatures)) {
				$features = Feature::findAllFeatures();
				foreach ($newSubscriptionFeatures as $newSubscriptionFeature) {
					$subscriptionFeature = new SubscriptionFeature();
					$subscriptionFeature->subscription_id = $this->id;
					$subscriptionFeature->feature_id = $features[$newSubscriptionFeature]->id;
					$subscriptionFeature->name = $newSubscriptionFeature;
					foreach($availableSubscriptionFeatures as $packageFeature) {
						if ($packageFeature->name == $newSubscriptionFeature) {
							$subscriptionFeature->value = (string)($packageFeature->value ?: 0);
						}
					}
					$subscriptionFeature->renewable = $features[$newSubscriptionFeature]->renewable ?: 0;
					$subscriptionFeatures[] = $subscriptionFeature;
				}
			}

			foreach ($subscriptionFeatures as $subscriptionFeature) {
				if (in_array($subscriptionFeature->subscription->package->type, [Package::TYPE_FREE, Package::TYPE_STANDARD]) || ($subscriptionFeature->subscription->package->type == Package::TYPE_CUSTOM && in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::COURSE]))) {
					if (!$subscriptionFeature->save()) {
						$this->addErrors($subscriptionFeature->getErrors());
						throw new \Exception('Cannot save the SubscriptionFeature models.');
					}
				}
			}

			$availableSubscriptionFeatures = $this->package->getPackageFeatures()
				->where([
					'AND',
					['IS NOT', 'value', null],
					['>', 'value', 0]
				])
				->indexBy('name')
				->all();
			$existingSubscriptionFeatures = $this->getSubscriptionFeatures()
				->where([
					'OR',
					['IS', 'value', null],
					['=', 'value', 0]
				])
				->indexBy('name')
				->all();
			$emptySubscriptionFeatures = array_intersect(array_keys($availableSubscriptionFeatures), array_keys($existingSubscriptionFeatures));
			$subscriptionFeatures = [];

			if (!empty($emptySubscriptionFeatures)) {
				$features = Feature::findAllFeatures();
				foreach ($emptySubscriptionFeatures as $emptySubscriptionFeature) {
					$subscriptionFeature = SubscriptionFeature::findOne([
						'subscription_id' => $this->id,
						'name' => $emptySubscriptionFeature,
					]);
					foreach($availableSubscriptionFeatures as $packageFeature) {
						if ($packageFeature->name == $emptySubscriptionFeature) {
							$subscriptionFeature->value = (string)($packageFeature->value ?: 0);
						}
					}
					$subscriptionFeature->renewable = $features[$emptySubscriptionFeature]->renewable ?: 0;
					$subscriptionFeatures[] = $subscriptionFeature;
				}
			}

			foreach ($subscriptionFeatures as $subscriptionFeature) {
				if (in_array($subscriptionFeature->subscription->package->type, [Package::TYPE_FREE, Package::TYPE_STANDARD]) || ($subscriptionFeature->subscription->package->type == Package::TYPE_CUSTOM && in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::COURSE]))) {
					if (!$subscriptionFeature->save()) {
						$this->addErrors($subscriptionFeature->getErrors());
						throw new \Exception('Cannot save the SubscriptionFeature models.');
					}
				}
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
			$workspaces = Workspace::find()
				->alias('w')
				->select([
					'w.*',
				])
				->joinWith([
					'subscription.package p' => function (ActiveQuery $query) {
						$query->andOnCondition([
							'p.status' => Workspace::STATUS_ACTIVE,
							'p.deleted' => Workspace::NO,
						]);
					},
				])
				->where([
					'p.id' => $this->package->id
				])
				->all();
			if (!empty($workspaces)) {
				foreach ($workspaces as $workspace) {
					/** @var SubscriptionFeature[] $subscriptionFeatures */
					$subscriptionFeatures = array_diff_key(
						$this->getSubscriptionFeatures()->indexBy('id')->all(),
						$workspace->getWorkspaceHasSubscriptionFeatures()->indexBy('subscription_feature_id')->all()
					);

					if (!empty($subscriptionFeatures)) {
						$attributes = ['workspace_id', 'subscription_feature_id'];
						$rows = [];
						foreach ($subscriptionFeatures as $subscriptionFeature) {
							if (in_array($subscriptionFeature->subscription->package->type, [Package::TYPE_FREE, Package::TYPE_STANDARD]) || ($subscriptionFeature->subscription->package->type == Package::TYPE_CUSTOM && in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::ACQUISITION, Feature::COURSE]))) {
								$rows[] = [
									'workspace_id' => $workspace->id,
									'subscription_feature_id' => $subscriptionFeature->id,
								];
								$workspaceDb = $workspace->getWorkspaceDb();
								$workspaceInstallDbPath = Yii::getAlias("@workspace/install/db");
								$workspaceDb->createCommand()->setRawSql(file_get_contents("{$workspaceInstallDbPath}/_04_translations.sql"))->execute();
								$workspaceUpgradeDbPath = Yii::getAlias("@workspace/upgrade/db");
								if (in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::ACQUISITION, Feature::COURSE])) {
									$workspaceDb->createCommand()->setRawSql(file_get_contents("{$workspaceUpgradeDbPath}/{$subscriptionFeature->name}.sql"))->execute();
								}
							}
						}
					}
				}

				if (!empty($rows) && !Yii::$app->db->createCommand()->batchInsert(WorkspaceHasSubscriptionFeature::tableName(), $attributes, $rows)->execute()) {
					throw new \Exception();
				}

				/** @var SubscriptionFeature[] $subscriptionFeatures */
				$emptySubscriptionFeatures = array_intersect_key(
					$this->getSubscriptionFeatures()
						->andWhere([
							'renewable' => Feature::YES
						])
						->indexBy('id')
						->all(),
					$workspace->getWorkspaceHasSubscriptionFeatures()
						->indexBy('subscription_feature_id')
						->all()
				);
				if (!empty($emptySubscriptionFeatures)) {
					foreach ($emptySubscriptionFeatures as $emptySubscriptionFeature) {
						$subscriptionFeature = SubscriptionFeature::findOne(['id' => $emptySubscriptionFeature->id]);
						if (in_array($subscriptionFeature->subscription->package->type, [Package::TYPE_FREE, Package::TYPE_STANDARD]) || ($subscriptionFeature->subscription->package->type == Package::TYPE_CUSTOM && in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::ACQUISITION, Feature::COURSE]))) {
							$workspaceDb = $workspace->getWorkspaceDb();
							$workspaceInstallDbPath = Yii::getAlias("@workspace/install/db");
							$workspaceDb->createCommand()->setRawSql(file_get_contents("{$workspaceInstallDbPath}/_04_translations.sql"))->execute();
							$workspaceUpgradeDbPath = Yii::getAlias("@workspace/upgrade/db");
							if (in_array($subscriptionFeature->name, [Feature::INVOICING_INVOICES, Feature::MARKETPLACE_PRODUCTS, Feature::APPOINTMENT, Feature::CONSIGNMENT, Feature::ACQUISITION, Feature::COURSE])) {
								$workspaceDb->createCommand()->setRawSql(file_get_contents("{$workspaceUpgradeDbPath}/{$subscriptionFeature->name}.sql"))->execute();
							}
						}
					}
				}
			}
            return true;
		} catch (\Exception $e) {
			return false;
		}
	}

    /**
     * Insert the WsTemplates for STORE models.
     *
     * @return bool
     */
    public function saveWorkspaceHasFeatureTemplates()
    {
        try {
            $workspaces = Workspace::find()
                ->alias('w')
                ->select([
                    'w.*',
                ])
                ->joinWith([
                    'subscription.package p' => function (ActiveQuery $query) {
                        $query->andOnCondition([
                            'p.status' => Workspace::STATUS_ACTIVE,
                            'p.deleted' => Workspace::NO,
                        ]);
                    },
                ])
                ->where([
                    'p.id' => $this->package->id
                ])
                ->all();


            if (!empty($workspaces)) {
                foreach ($workspaces as $workspace) {
                    /** @var SubscriptionFeature[] $subscriptionFeatures */
                    $subscriptionFeatures = array_diff_key(
                        $this->getSubscriptionFeatures()->indexBy('id')->all(),
                        $workspace->getWorkspaceHasSubscriptionFeatures()->indexBy('subscription_feature_id')->all()
                    );

                    $wsTemplates = WsTemplate::find()
                        ->alias('wt')
                        ->select([
                            'wt.*',
                        ])
                        ->joinWith([
                            'templateTranslations wtt' => function (ActiveQuery $query) {
                                $query->andOnCondition([
                                    'wtt.deleted' => Workspace::NO,
                                ]);
                            },
                        ])
                        ->andWhere([
                            'wt.section' => $subscriptionFeatures,
                        ])
                        ->all();

                    if (!empty($subscriptionFeatures)) {
                        // batchInsert() bypasses the models, so the UUID v7 ids are generated
                        // here; the insert runs ONCE after the loop — inside it, every
                        // iteration re-inserted all previously collected rows.
                        $attributes = ['id', 'type', 'variant', 'section', 'default', 'deleted'];
                        $rows = [];
                        foreach ($wsTemplates as $wsTemplate) {
                            $rows[] = [
                                'id' => \common\helpers\UuidHelper::uuid7Bytes(),
                                'type' => $wsTemplate->type,
                                'variant' => $wsTemplate->variant,
                                'section' => $wsTemplate->section,
                                'default' => $wsTemplate->default,
                                'deleted' => $wsTemplate->deleted,
                            ];
                        }

                        if (!empty($rows) && !Yii::$app->db->createCommand()->batchInsert(Template::tableName(), $attributes, $rows)->execute()) {
                            throw new \Exception();
                        }
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }



	/**
	 * Saves the model.
	 *
	 * @return bool
	 */
	public function saveModel()
	{
		$dbTransaction = static::getDb()->beginTransaction();
		try {
			if (!$this->saveSubscriptionFeatures()) {
				throw new \Exception();
			}
			if (!$this->saveWorkspaceHasSubscriptionFeatures()) {
				throw new \Exception();
			}
			if (!$this->saveWorkspaceHasFeatureTemplates()) {
			    throw new \Exception();
            }
			$dbTransaction->commit();
			return true;
		} catch(\Exception $e) {
			$dbTransaction->rollBack();
			return false;
		}
	}
}
