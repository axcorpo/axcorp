<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use tws\helpers\Url;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%page}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property string $module
 * @property string $controller
 * @property string $action
 * @property string $image
 * @property int $sort_order
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property MenuItem[] $menuItems
 * @property Page $parent
 * @property Page[] $pages
 * @property PageTranslation[] $pageTranslations
 * @property PageTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class Page extends UuidActiveRecord
{
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%page}}';
	}

	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'BlameableBehavior' => [
				'class' => BlameableBehavior::class,
			],
			'TimestampBehavior' => [
				'class' => TimestampBehavior::class,
				'value' => (new \DateTime)->format('Y-m-d H:i:s'),
			],
			'SoftDeleteBehavior' => [
				'class' => SoftDeleteBehavior::class,
				'softDeleteAttributeValues' => [
					'deleted' => static::YES,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules()
	{
		return [
			[['parent_id', 'sort_order', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['controller', 'action', 'status'], 'required'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['module', 'controller', 'action', 'image'], 'string', 'max' => 255],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => Page::class, 'targetAttribute' => ['parent_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'parent_id' => Yii::t('label', 'Parent ID'),
			'module' => Yii::t('label', 'Module'),
			'controller' => Yii::t('label', 'Controller'),
			'action' => Yii::t('label', 'Action'),
			'image' => Yii::t('label', 'Image'),
			'sort_order' => Yii::t('label', 'Sort Order'),
			'default' => Yii::t('label', 'Default'),
			'created_by' => Yii::t('label', 'Created By'),
			'updated_by' => Yii::t('label', 'Updated By'),
			'created_at' => Yii::t('label', 'Created At'),
			'updated_at' => Yii::t('label', 'Updated At'),
			'status' => Yii::t('label', 'Status'),
			'deleted' => Yii::t('label', 'Deleted'),
		];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMenuItems()
	{
		return $this->hasMany(MenuItem::class, ['page_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getParent()
	{
		return $this->hasOne(Page::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPages()
	{
		return $this->hasMany(Page::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getPageTranslations()
	{
		return $this->hasMany(PageTranslation::class, ['page_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|PageTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->pageTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%page_translation}}', ['page_id' => 'id']);
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
	 * Gets the page route.
	 *
	 * @return string
	 */
	public function getRoute()
	{
		return '/' . implode('/', array_filter([
			$this->module,
			$this->controller,
			$this->action,
		]));
	}

	/**
	 * Gets the imageUrl.
	 *
	 * @param bool $scheme
	 * @return string
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/page/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Gets translated description with its shortcode values.
	 *
	 * @param string|null $language_id
	 * @return string
	 */
	public function getDescription($language_id = null)
	{
		if (empty($language_id)) {
			$language_id = Yii::$app->language;
		}

		return strtr($this->getTranslation($language_id)->description, $this->getShortCodeValues());
	}

	/**
	 * Gets translated content with its shortcode values.
	 *
	 * @param string|null $language_id
	 * @return string
	 */
	public function getContent($language_id = null)
	{
		if (empty($language_id)) {
			$language_id = Yii::$app->language;
		}

		return strtr($this->getTranslation($language_id)->content, $this->getShortCodeValues());
	}

	/**
	 * Gets the model available short codes.
	 *
	 * @return array
	 */
	public static function getShortCodeItems()
	{
		return [
			'{{APP_NAME}}' => Yii::t('label', 'Application Name'),
			'{{APP_HOST}}' => Yii::t('label', 'Application Host'),
			'{{APP_URL}}' => Yii::t('label', 'Application URL'),
			'{{APP_LOGO_URL}}' => Yii::t('label', 'Application Logo URL'),
			'{{APP_LOGO_ALT_URL}}' => Yii::t('label', 'Application Logo Alternative URL'),
			'{{COMPANY_NAME}}' => Yii::t('label', 'Company Name'),
			'{{COMPANY_TIN}}' => Yii::t('label', 'Company Taxpayer Identification Number'),
			'{{COMPANY_REG_NO}}' => Yii::t('label', 'Company Registration Number'),
			'{{COMPANY_LEGAL_REPRESENTATIVE}}' => Yii::t('label', 'Company Legal Representative'),
			'{{COMPANY_EMAIL}}' => Yii::t('label', 'Company Email'),
			'{{COMPANY_PHONE}}' => Yii::t('label', 'Company Phone'),
			'{{COMPANY_FAX}}' => Yii::t('label', 'Company Fax'),
			'{{COMPANY_ADDRESS}}' => Yii::t('label', 'Company Address'),
		];
	}

	/**
	 * Gets the values for the model available short codes.
	 *
	 * @return array
	 */
	public function getShortCodeValues()
	{
		$generalSettings = Yii::$app->settings->getCategory('general');
		$contactSettings = Yii::$app->settings->getCategory('contact');
		$shortCodes = [
			'{{APP_NAME}}' => Yii::$app->name,
			'{{APP_HOST}}' => parse_url($generalSettings['hostInfo'], PHP_URL_HOST),
			'{{APP_URL}}' => Url::to(['/site/index'], true, '@frontend'),
			'{{APP_LOGO_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogo'), true) ?: Url::to('@frontend/web/img/logo.png', true),
			'{{APP_LOGO_ALT_URL}}' => Url::to('@uploads/' . Yii::$app->settings->get('appLogoAlt'), true) ?: Url::to('@frontend/web/img/logo-alt.png', true),
			'{{COMPANY_NAME}}' => $contactSettings['company'],
			'{{COMPANY_TIN}}' => $contactSettings['tin'],
			'{{COMPANY_REG_NO}}' => $contactSettings['registrationNumber'],
			'{{COMPANY_LEGAL_REPRESENTATIVE}}' => implode(' ', array_filter([
				$contactSettings['firstName'],
				$contactSettings['middleName'],
				$contactSettings['lastName'],
			])),
			'{{COMPANY_EMAIL}}' => $contactSettings['email'],
			'{{COMPANY_PHONE}}' => $contactSettings['mobilePhone'] ?: $contactSettings['fixedPhone'],
			'{{COMPANY_FAX}}' => $contactSettings['fax'],
			'{{COMPANY_ADDRESS}}' => implode(', ', array_filter([
				$contactSettings['streetName'],
				$contactSettings['streetNumber'],
				$contactSettings['locality'],
				$contactSettings['zipCode'],
				$contactSettings['county'],
				$contactSettings['country'] ? \common\models\Country::findAllCountries()[$contactSettings['country']]->translation->name : null,
			])),
		];

		return $shortCodes;
	}

	/**
	 * Finds all active records.
	 *
	 * @return self[]|array
	 */
	public static function findAllPages()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('p')
					->joinWith([
						'pageTranslations pt' => function (ActiveQuery $query) {
							$query->andOnCondition([
								'pt.language_id' => Yii::$app->language,
								'pt.deleted' => PartnerTranslation::NO,
							]);
						},
					])
					->where([
						'p.status' => self::STATUS_ACTIVE,
						'p.deleted' => self::NO,
					])
					->addOrderBy(['pt.title' => SORT_ASC])
					->indexBy('id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Finds models by module.
	 *
	 * @param string $module
	 * @return array|\yii\db\ActiveRecord[]|self[]|null
	 */
	public static function findPagesByModule($module)
	{
		$query = static::find()
			->alias('p')
			->joinWith([
				'pageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'p.status' => self::STATUS_ACTIVE,
				'p.deleted' => self::NO,
			])
			->addOrderBy(['pt.title' => SORT_ASC])
			->indexBy('id');

		if ($module == 'application') {
			$query->andWhere(['IS', 'p.module', null]);
		} else {
			$query->andWhere(['p.module' => $module]);
		}

		return $query->all();
	}

	/**
	 * Finds model by slug.
	 *
	 * @param string $slug
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findPageBySlug($slug)
	{
		return static::find()
			->alias('p')
			->joinWith([
				'pageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PageTranslation::NO,
					]);
				},
			])
			->andWhere([
				'p.status' => self::STATUS_ACTIVE,
				'p.deleted' => self::NO,
				'pt.slug' => $slug,
			])
			->limit(1)
			->one();
	}

	/**
	 * Finds model by route.
	 *
	 * @param array|string $route The route composed by controller and action.
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findPageByRoute($route)
	{
		if (is_array($route)) {
			$route = $route[0];
		}
		$segments = explode('/', ltrim($route, '/'));
		$module = null;
		if (count($segments) === 3) {
			list($module, $controller, $action) = $segments;
		} else {
			list($controller, $action) = $segments;
		}

		return static::find()
			->alias('p')
			->joinWith([
				'pageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
//						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PageTranslation::NO,
					]);
				}
			])
			->andWhere([
				'p.controller' => $controller,
				'p.action' => $action,
				'p.status' => self::STATUS_ACTIVE,
				'p.deleted' => self::NO,
			])
			->andFilterWhere([
				'p.module' => $module,
			])
			->limit(1)
			->one();
	}

	/**
	 * Finds model by URL pathInfo.
	 *
	 * @param array|string $pathInfo The URL pathInfo.
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findPageByPathInfo($pathInfo)
	{
		$segments = array_filter(is_array($pathInfo) ? $pathInfo : explode('/', ltrim($pathInfo, '/')));
		$segmentsCount = count($segments);
		$slug = $segments[$segmentsCount - 1];

		if ($segmentsCount === 0) {
			// Find home page
			return self::findPageByRoute(['/site/index']);
		} elseif ($segmentsCount === 1) {
			// Try to find the module default page
			if ($page = self::findPageByRoute(["/{$segments[0]}/default/index"])) {
				return $page;
			}
			// Or find page by slug
			return self::findPageBySlug($slug);
		}
		// Find page by module and slug
		return static::find()
			->alias('p')
			->joinWith([
				'pageTranslations pt' => function (ActiveQuery $query) {
					$query->andOnCondition([
						'pt.language_id' => Yii::$app->language,
						'pt.deleted' => PageTranslation::NO,
					]);
				}
			])
			->andWhere([
				'p.status' => self::STATUS_ACTIVE,
				'p.deleted' => self::NO,
				'p.module' => Yii::$app->controller->module->id,
				'pt.slug' => $slug,
			])
			->limit(1)
			->one();
	}
}
