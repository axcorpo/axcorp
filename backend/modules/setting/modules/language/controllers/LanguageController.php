<?php

namespace backend\modules\setting\modules\language\controllers;

use backend\controllers\MainController;
use backend\modules\setting\modules\language\assets\LanguageTranslateAsset;
use backend\modules\setting\modules\language\models\LanguageSearch;
use backend\modules\setting\modules\language\models\LanguageTranslationSearch;
use common\models\Language;
use common\models\LanguageSource;
use common\models\LanguageTranslate;
use Yii;
use yii\db\ActiveQuery;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

class LanguageController extends MainController
{
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::class,
				'rules' => [
					[
						'allow' => true,
						'actions' => ['index', 'dt-languages'],
						'roles' => ['viewLanguage'],
					],
					[
						'allow' => true,
						'actions' => ['translate', 'dt-language-translations'],
						'roles' => ['translateIntoLanguage'],
					],
				],
			],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'dt-languages' => LanguageSearch::class,
			'dt-language-translations' => LanguageTranslationSearch::class,
		];
	}

	/**
	 * Lists all Language models.
	 *
	 * @return mixed
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	public function actionIndex()
	{
		if (Yii::$app->request->isAjax && Yii::$app->request->post('dt')) {
			return $this->updateDtColumn();
		}

		return $this->render('index');
	}

	/**
	 * Lists all LanguageSource models.
	 *
	 * @param string $id
	 * @return mixed
	 */
	public function actionTranslate($id)
	{
		LanguageTranslateAsset::register($this->view);

		if (Yii::$app->request->isAjax && Yii::$app->request->post('dt')) {
			return $this->saveTranslation($id);
		}

		if (Yii::$app->request->get('translator')) {
			$records = LanguageSource::find()
				->alias('ls')
				->joinWith([
					'languageTranslates lt' => function (ActiveQuery $query) {
						$query->andOnCondition(['lt.language' => Yii::$app->request->get('id')]);
					},
				])
				->asArray()
				->all();
			$counter = count($records);
			$source = Language::findOne(['language_id' => 'en-US'])->language;
			$target = Language::findOne(['language_id' => Yii::$app->request->get('id')])->language;

			$messages = array_map(static function ($record) {
				return (string)($record['message'] ?? '');
			}, $records);

			try {
				/** @var \common\components\GoogleTranslation $translator */
				$translator = Yii::$app->translate;
				$translations = $translator->translateMany($source, $target, $messages);
				foreach ($translations as $i => $translated) {
					if (!mb_check_encoding($translated, 'UTF-8')) {
						$translations[$i] = mb_convert_encoding($translated, 'UTF-8', 'UTF-8');
					}
					$translations[$i] = trim($translations[$i]);
				}
			} catch (\Exception $e) {
				Yii::$app->session->setFlash('error', $e->getMessage());
				return $this->redirect(['translate', 'id' => Yii::$app->request->get('id')]);
			}

			if (count($translations) === $counter) {
				try {
					$languageId = (string)Yii::$app->request->get('id');
					$overwrite = filter_var(
						Yii::$app->request->get('overwrite'),
						FILTER_VALIDATE_BOOLEAN,
						FILTER_NULL_ON_FAILURE
					) === true;

					for ($i = 0; $i < $counter; $i++) {
						$model = LanguageTranslate::findOne([
							'id' => (int)$records[$i]['id'],
							'language' => $languageId,
						]);
						if ($model !== null && !$overwrite) {
							continue;
						}
						if ($model === null) {
							$model = new LanguageTranslate();
						}
						$model->id = (int)$records[$i]['id'];
						$model->language = $languageId;
						$model->translation = $translations[$i];
						if (!$model->save(false)) {
							throw new \Exception(Yii::t('common', 'Translation failed.'));
						}
					}

					Yii::$app->trigger('invalidate.cache');
					$this->clearI18nMessageCache();

					Yii::$app->session->setFlash('success', Yii::t('common', 'Translation successful.'));
					return $this->redirect(['translate', 'id' => $languageId]);
				} catch (\Exception $e) {
					Yii::$app->session->setFlash('error', $e->getMessage());
					return $this->redirect(['translate', 'id' => Yii::$app->request->get('id')]);
				}
			} else {
				Yii::$app->session->setFlash('error', Yii::t('common', 'Translation failed.'));
				return $this->redirect(['translate', 'id' => Yii::$app->request->get('id')]);
			}
		}

		return $this->render('translate', [
			'categories' => ArrayHelper::map(LanguageSource::find()->all(), 'category', 'category'),
		]);
	}

	/**
	 * Finds the Language model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @return Language the loaded model
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel($id)
	{
		if (($model = Language::findOne($id)) !== null) {
			return $model;
		}

		throw new NotFoundHttpException(Yii::t('common', 'The requested page does not exist.'));
	}

	/**
	 * Updates DataTable column.
	 *
	 * @return \yii\web\Response
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function updateDtColumn()
	{
		$params = Yii::$app->request->post();
		$result = false;

		$model = $this->findModel($params['key']);

		Yii::$app->eventLog
			->setData([
				'operation' => (Yii::$app->eventLog)::ACTION_UPDATE,
			])
			->beginRecord($model);

		if ($model->hasAttribute($params['attribute'])) {
			$model->{$params['attribute']} = $params['value'];

			if ($result = $model->save(true, [$params['attribute']])) {
				Yii::$app->eventLog->endRecord();
			}
		}

		Yii::$app->trigger('invalidate.cache', new \tws\caching\CacheEvent(['key' => 'findAllLanguages']));

		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		]);
	}

	/**
	 * Updates translation column.
	 *
	 * @param string $language_id
	 * @return \yii\web\Response
	 */
	protected function saveTranslation($language_id)
	{
		$params = Yii::$app->request->post();

		$model = LanguageTranslate::findOne([
			'id' => $params['key'] ?? null,
			'language' => $language_id,
		]);
		if (!$model) {
			$model = new LanguageTranslate();
			$model->id = $params['key'] ?? null;
			$model->language = $language_id;
		}

		$result = false;
		if ($model->hasAttribute($params['attribute'] ?? '')) {
			// Ensure UTF-8 encoding to prevent JSON errors
			$value = $params['value'] ?? '';
			if (!mb_check_encoding($value, 'UTF-8')) {
				$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
			}
			$model->{$params['attribute']} = $value;
			$result = $model->save();
			Yii::$app->trigger('invalidate.cache');
		}

		return $this->asJson([
			'success' => (bool) $result,
			'message' => $result ?
				Yii::t('common', 'Record has been updated.') :
				Yii::t('common', 'Cannot update the record.'),
		]);
	}

	/**
	 * Clears Yii DB message source cache so bulk/auto translations appear immediately.
	 */
	protected function clearI18nMessageCache(): void
	{
		foreach (array_keys(Yii::$app->i18n->translations) as $category) {
			$messageSource = Yii::$app->i18n->getMessageSource($category);
			if ($messageSource instanceof \yii\i18n\DbMessageSource && method_exists($messageSource, 'clearCache')) {
				$messageSource->clearCache();
			}
		}
	}
}
