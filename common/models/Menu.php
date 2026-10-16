<?php

namespace common\models;

use tws\behaviors\DefaultBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%menu}}".
 *
 * @property int $id
 * @property int $position
 * @property int $default
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property MenuItem[] $menuItems
 * @property MenuTranslation[] $menuTranslations
 * @property MenuTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class Menu extends UuidActiveRecord
{
	const POSITION_HEADER = 1;
	const POSITION_TOPBAR = 2;
	const POSITION_SIDEBAR = 3;
	const POSITION_FOOTER = 4;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%menu}}';
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
			'DefaultBehavior' => [
				'class' => DefaultBehavior::class,
				'groupAttributes' => ['position'],
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
			[['position', 'default', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('label', 'ID'),
			'position' => Yii::t('label', 'Position'),
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
		return $this->hasMany(MenuItem::class, ['menu_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getMenuTranslations()
	{
		return $this->hasMany(MenuTranslation::class, ['menu_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|MenuTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->menuTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%menu_translation}}', ['menu_id' => 'id']);
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
	 * Gets the formattedPosition.
	 *
	 * @param string $glue
	 * @return string
	 */
	public function getFormattedPosition($glue = ', ')
	{
		return implode($glue, ArrayHelper::filter(self::getPositionLabels(), explode(',', $this->position)));
	}

	/**
	 * Gets the nested items.
	 *
	 * @param null|array $columns The columns for each items from the tree array.
	 * @param null|int $status The status of the record.
	 * @param null|int $deleted The deleted state of the record.
	 * @return array
	 */
	public function getNestedItems($columns = null, $status = null, $deleted = null, $language = null, $cacheDuration = 30 * 24 * 3600)
	{
		$cacheKey = 'nested_menu_items_' . $this->id . '_' . ($language ?: Yii::$app->language) . '_' . md5(serialize([
				'columns' => $columns,
				'status' => $status,
				'deleted' => $deleted,
			]));
		$cachedItems = Yii::$app->cache->get($cacheKey);
		if ($cachedItems !== false) {
			return $cachedItems; // Return cached items if available
		}
		$parentMenuItems = $this->getMenuItems()
			->where([
				'parent_id' => null,
				'deleted' => $deleted ?: MenuItem::NO,
			])
			->andFilterWhere([
				'status' => $status,
			])
			->orderBy(new Expression('[[sort_order]] IS NULL'))
			->addOrderBy(['sort_order' => SORT_ASC])
			->all();
		$items = $this->getItemsWithChildren($parentMenuItems, $columns, $status, $deleted);
		Yii::$app->cache->set($cacheKey, $items, $cacheDuration, new TagDependency(['tags' => 'menu_' . $this->id . '_' . ($language ?: Yii::$app->language)]));
		return $items;
	}

	/**
	 * Gets the menu items with their children.
	 *
	 * @param MenuItem[] $menuItems
	 * @param null|string|array $columns The columns of the each item.
	 * @param null|int $status The status of the record.
	 * @param null|int $deleted The deleted state of the record.
	 * @return array
	 */
	public function getItemsWithChildren($menuItems, $columns = null, $status = null, $deleted = null, $language = null, $cacheDuration = 30 * 24 * 3600)
	{
		$cacheKey = 'menu_items_with_children_' . $this->id . '_' . ($language ?: Yii::$app->language) . '_' . md5(serialize([
				'menuItems' => $menuItems,
				'columns' => $columns,
				'status' => $status,
				'deleted' => $deleted,
			]));
		$cachedItems = Yii::$app->cache->get($cacheKey);
		if ($cachedItems !== false) {
			return $cachedItems;
		}
		$items = [];
		foreach ($menuItems as $menuItem) {
			$item = [
				// Canonical UUID string, never the raw BINARY(16) bytes: this value is
				// rendered into the sortable widget's data-id attribute and posted back.
				'id' => $menuItem->uuid,
				'content' => $menuItem->page_id ? $menuItem->page->translation->title : $menuItem->translation->title,
				'model' => $menuItem,
			];
			if (!empty($columns)) {
				$item = array_intersect_key($item, array_fill_keys((array)$columns, null));
			}
			$childMenuItems = $menuItem->getMenuItems()
				->andWhere(['deleted' => $deleted ?: MenuItem::NO])
				->andFilterWhere(['status' => $status])
				->orderBy(new Expression('[[sort_order]] IS NULL'))
				->addOrderBy(['sort_order' => SORT_ASC])
				->all();
			if ($childMenuItems) {
				$item['items'] = $this->getItemsWithChildren($childMenuItems, $columns, $status, $deleted, $cacheDuration);
			}
			$items[] = $item;
		}
		Yii::$app->cache->set($cacheKey, $items, $cacheDuration, new TagDependency(['tags' => 'menu_' . $this->id . '_' . ($language ?: Yii::$app->language)]));
		return $items;
	}

	/**
	 * Model position labels.
	 *
	 * @return array
	 */
	public static function getPositionLabels()
	{
		return [
			self::POSITION_HEADER => Yii::t('label', 'Header'),
			self::POSITION_TOPBAR => Yii::t('label', 'Top Bar'),
			self::POSITION_SIDEBAR => Yii::t('label', 'Sidebar'),
			self::POSITION_FOOTER => Yii::t('label', 'Footer'),
		];
	}

	/**
	 * Finds the default record by position or fallback to the first record.
	 *
	 * @param int $position The menu position.
	 * @param bool $fallbackToFirst Indicates if the first record should be returned when a default one does not exist.
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultMenuByPosition($position, $fallbackToFirst = true)
	{
		$query = static::find()
			->andWhere([
				'position' => $position,
				'status' => self::STATUS_ACTIVE,
				'deleted' => self::NO,
			])
			->limit(1);

		if (!($model = $query->andWhere(['default' => self::YES])->one())) {
			if ($fallbackToFirst === true) {
				$model = $query->one();
			}
		}

		return $model;
	}

	/**
	 * Gets the default header menu.
	 *
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultHeaderMenu()
	{
		return self::findDefaultMenuByPosition(self::POSITION_HEADER);
	}

	/**
	 * Gets the default top bar menu.
	 *
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultTopBarMenu()
	{
		return self::findDefaultMenuByPosition(self::POSITION_TOPBAR);
	}

	/**
	 * Gets the default top bar menu.
	 *
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultSidebarMenu()
	{
		return self::findDefaultMenuByPosition(self::POSITION_SIDEBAR);
	}

	/**
	 * Gets the default footer menu.
	 *
	 * @return array|\yii\db\ActiveRecord|self|null
	 */
	public static function findDefaultFooterMenu()
	{
		return self::findDefaultMenuByPosition(self::POSITION_FOOTER);
	}

	public static function invalidateMenuCache($menuItemId, $menuId = null)
	{
		$tags = [
			'getItemsWithParents',
			'getNestedItems',
			'getItemsWithChildren',
			'menu_item_' . $menuItemId,
		];
		foreach (\common\models\Language::findAllLanguages() as $language) {
			$tags[] = 'menu_' . $menuId . '_' . $language->language_id;
			$tags[] = 'menu_item_' . $menuItemId . '_' . $language->language_id;
		}
		TagDependency::invalidate(Yii::$app->cache, $tags);
	}
}
