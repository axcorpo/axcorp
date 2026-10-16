<?php

namespace common\models;

use common\models\TutorialCategoryTranslation;
use tws\helpers\Url;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii2tech\ar\position\PositionBehavior;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * This is the model class for table "{{%tutorial_category}}".
 *
 * @property int $id
 * @property int $parent_id
 * @property int $leaf
 * @property string $image
 * @property string $icon
 * @property int $type
 * @property int $sort_order
 * @property int $created_by
 * @property int $updated_by
 * @property string $created_at
 * @property string $updated_at
 * @property int $status
 * @property int $deleted
 *
 * @property TutorialCategory $parent
 * @property TutorialCategory[] $tutorialCategories
 * @property TutorialCategoryHasTutorial[] $tutorialCategoryHasTutorials
 * @property Tutorial[] $tutorials
 * @property TutorialCategoryTranslation[] $tutorialCategoryTranslations
 * @property TutorialCategoryTranslation $translation
 * @property Language[] $languages
 * @property User $creator
 * @property User $updater
 */
class TutorialCategory extends UuidActiveRecord
{
    private $categories = [];
	/**
	 * {@inheritdoc}
	 */
	public static function tableName()
	{
		return '{{%tutorial_category}}';
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
			'PositionBehavior' => [
				'class' => PositionBehavior::class,
				'positionAttribute' => 'sort_order',
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
			[['parent_id', 'leaf', 'type', 'sort_order', 'created_by', 'updated_by', 'status', 'deleted'], 'integer'],
			[['created_at', 'updated_at'], 'safe'],
			[['created_at', 'updated_at'], 'default'],
			[['status'], 'required'],
			[['image', 'icon'], 'string', 'max' => 255],
			[['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => TutorialCategory::class, 'targetAttribute' => ['parent_id' => 'id']],
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
			'leaf' => Yii::t('label', 'Leaf'),
			'image' => Yii::t('label', 'Image'),
			'icon' => Yii::t('label', 'Icon'),
			'type' => Yii::t('label', 'Type'),
			'sort_order' => Yii::t('label', 'Sort Order'),
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
	public function getParent()
	{
		return $this->hasOne(TutorialCategory::class, ['id' => 'parent_id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorialCategories()
	{
		return $this->hasMany(TutorialCategory::class, ['parent_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorialCategoryHasTutorials()
	{
		return $this->hasMany(TutorialCategoryHasTutorial::class, ['tutorial_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorials()
	{
		return $this->hasMany(Tutorial::class, ['id' => 'tutorial_id'])->viaTable('{{%tutorial_category_has_tutorial}}', ['tutorial_category_id' => 'id']);
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getTutorialCategoryTranslations()
	{
		return $this->hasMany(TutorialCategoryTranslation::class, ['tutorial_category_id' => 'id']);
	}

	/**
	 * Gets the model translation.
	 *
	 * @param null|string $language
	 * @return null|TutorialCategoryTranslation
	 */
	public function getTranslation($language = null)
	{
		if ($language === null) {
			$language = Yii::$app->language;
		}
		return ArrayHelper::index($this->tutorialCategoryTranslations, 'language_id')[$language];
	}

	/**
	 * @return \yii\db\ActiveQuery|CommonActiveQuery
	 */
	public function getLanguages()
	{
		return $this->hasMany(Language::class, ['language_id' => 'language_id'])->viaTable('{{%tutorial_category_translation}}', ['tutorial_category_id' => 'id']);
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
     * @param string $glue
     * @param int $type
     * @return string
     * @throws \Throwable
     */
    public function getTreeName($glue = ' &rsaquo; ')
    {
        return implode($glue, ArrayHelper::getColumn(self::getTree($this->id), 'translation.title'));
    }

	/**
	 * Gets the imageUrl.
	 *
	 * @param bool $scheme
	 * @return string|null
	 */
	public function getImageUrl($scheme = false)
	{
		return Url::to("@uploads/tutorial-category/{$this->uuid}/{$this->image}", $scheme);
	}

	/**
	 * Finds all active records.
	 *
	 * @return static[]|array
	 */
	public static function findAllTutorialCategories()
	{
		try {
			return static::getDb()->cache(function ($db) {
				return static::find()
					->alias('pc')
					->joinWith([
						'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
							$query->andOnCondition([
								'pct.language_id' => Yii::$app->language,
								'pct.deleted' => TutorialCategoryTranslation::NO,
							]);
						},
					])
					->andWhere([
						'pc.parent_id' => null,
						'pc.status' => static::STATUS_ACTIVE,
						'pc.deleted' => static::NO,
					])
					->orderBy(new Expression('[[pc.sort_order]] IS NULL'))
					->addOrderBy(['pc.sort_order' => SORT_ASC])
					->indexBy('id')
					->all();
			}, 0, new TagDependency(['tags' => __FUNCTION__]));
		} catch (\Throwable $e) {
			return [];
		}
	}

    /**
     * Finds all active records.
     *
     * @param null $category_id
     * @param int $type
     * @return mixed
     * @throws \Throwable
     */
    public static function findAllAvailableCategories($category_id)
    {
        $parentIds = ArrayHelper::getColumn(self::getTree($category_id), 'id');
        $parentIds = array_diff($parentIds, [$category_id]);
        return static::find()
            ->alias('pc')
            ->joinWith([
                'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'pct.language_id' => Yii::$app->language,
                        'pct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
            ])
            ->where([
                'pc.status' => self::STATUS_ACTIVE,
                'pc.deleted' => self::NO,
            ])
            ->andFilterWhere(['<>', 'pc.id', $category_id])
            ->andFilterWhere(['IN', 'pc.id', $parentIds])
            ->orderBy(new Expression('pc.sort_order IS NULL'))
            ->addOrderBy(['pc.sort_order' => SORT_ASC])
            ->all();
    }

    /**
     * Finds all active records.
     *
     * @param int $category_id
     * @param int $type
     * @return mixed
     * @throws \Throwable
     */
    public static function getTree($category_id)
    {
        $tree = [];
        if (!$category_id) {
            return $tree;
        }
        $model = static::find()
            ->alias('pc')
            ->joinWith([
                'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'pct.language_id' => Yii::$app->language,
                        'pct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
            ])
            ->where([
                'pc.deleted' => self::NO,
                'pc.id' => $category_id,
            ])
            ->one();
        if ($model === null) {
            return $tree;
        }
        $tree[$model->id] = $model;
        if ($model->parent_id) {
            $tree = array_merge($tree, array_reverse(self::getTree($model->parent_id)));
        }
        return array_reverse($tree);
    }

	/**
	 * Finds model by slug.
	 *
	 * @param string $criteria
	 * @param bool $asArray
	 * @return array|static[]]
	 */
	public static function findByCriteria($criteria, $asArray = false)
	{
		return static::find()
			->alias('pc')
			->joinWith([
				'tutorialCategoryTranslations pct' => function (ActiveQuery $query) use ($criteria) {
					$query->andOnCondition([
						'pct.language_id' => Yii::$app->language,
						'pct.deleted' => TutorialCategoryTranslation::NO,
					]);
					$query->andWhere(['LIKE', 'pct.title', $criteria]);
				},
			])
			->andWhere([
				'pc.status' => static::STATUS_ACTIVE,
				'pc.deleted' => static::NO,
			])
			->orderBy(new Expression('[[pc.sort_order]] IS NULL'))
			->addOrderBy(['pc.sort_order' => SORT_ASC])
			->asArray($asArray)
			->indexBy('id')
			->all();
	}

    /**
     * @param $parent_id
     * @param int $level
     * @param null $status
     * @return array
     */
    public static function getChildren($parent_id, $level = 0, $status = null)
    {
        $children = [];
        /** @var \common\models\TutorialCategory[] $categories */
        $query = static::find()
            ->alias('pc')
            ->joinWith([
                'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'pct.language_id' => Yii::$app->language,
                        'pct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
            ])
			->orderBy(new Expression('[[pc.sort_order]] IS NULL'))
			->addOrderBy(['pc.sort_order' => SORT_ASC])
            ->where([
                'pc.parent_id' => $parent_id,
                'pc.deleted' => self::NO,
            ]);
        if ($status) {
            $query->andWhere([
                'pc.status' => $status
            ]);
        }
        $categories = $query->all();

        foreach ($categories as $category) {
            $children[] = $category;
            $children = array_merge($children, static::getChildren($category->id, $level + 1, $status));
        }

        return $children;
    }

    /**
     * @param $parent_id
     * @param int $level
     * @param null $status
     * @return array
     */
    public static function getCategoriesByParent($parent_id, $status = null)
    {
        $children = [];
        /** @var static[] $categories */
        $query = static::find()
            ->alias('pc')
            ->joinWith([
                'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'pct.language_id' => Yii::$app->language,
                        'pct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
            ])
			->orderBy(new Expression('[[pc.sort_order]] IS NULL'))
			->addOrderBy(['pc.sort_order' => SORT_ASC])
            ->where([
                'pc.parent_id' => $parent_id,
                'pc.deleted' => self::NO,
            ]);
        if ($status) {
            $query->andWhere([
                'pc.status' => $status
            ]);
        }
        $categories = $query->all();

        foreach ($categories as $category) {
            $children[] = $category;
        }

        return $children;
    }

    /**
     * @param $category_id
     * @param null $status
     * @return TutorialCategory[]
     */
    public static function getParents($category_id, $status = null)
    {
        $query = static::find()
            ->alias('pc')
            ->joinWith([
                'tutorialCategoryTranslations pct' => function (ActiveQuery $query) {
                    $query->andOnCondition([
                        'pct.language_id' => Yii::$app->language,
                        'pct.deleted' => TutorialCategoryTranslation::NO,
                    ]);
                },
            ])
			->orderBy(new Expression('[[pc.sort_order]] IS NULL'))
			->addOrderBy(['pc.sort_order' => SORT_ASC])
            ->where([
                'pc.id' => $category_id,
                'pc.deleted' => self::NO,
            ]);
        if ($status) {
            $query->andWhere([
                'pc.status' => $status
            ]);
        }
        $parent = $query->one();
        if ($parent->parent_id) {
            array_push($this->categories, $parent);
            static::getParents($parent->parent_id, $status);
        }
        return $this->categories;
    }

    /**
     * Treeview
     */
    public static function treeview($parent_id, $current = null, $status = null)
    {
        // $current may arrive as a canonical UUID string; the comparisons below are
        // against raw BINARY(16) ids, so normalize it to bytes first.
        if ($current && \common\helpers\UuidHelper::isValid($current)) {
            $current = \common\helpers\UuidHelper::toBytes($current);
        }
        $categories = static::getCategoriesByParent($parent_id, $status);
        $output = '';
        if ($categories) {
            $output  .= '';
        }
        foreach ($categories as $category) {
            $list_class = '';
            $uls = '';
            $ule = '';
            $divs = '';
            $dive = '';
            $btn = '';
            $children = self::treeview($category->id, $current, $status);
            if ($children) {
                $list_class .= 'has-subtree ';
                if(($current && in_array($category->id, ArrayHelper::getColumn(static::getParents($current, $status), 'id'))) || ($current && $current == $category->id)) {
                    $list_class .= 'subtree-open ';
                }
                $uls .= '<ul class="subtree">';
                $ule .= '</ul>';
                $divs = '<div class="subtree-header">';
                $dive = '</div>';
                $btn = '<button type="button" class="btn btn-subtree-toggle">
								<span class="fa fa-plus"></span>
						</button>';
            } else {
                if($current == $category->id) {
                    $list_class .= 'active ';
                }
            }
            $output .= '<li class="' . $list_class . '">';
            $output .= $divs;
            $output .= Html::a(ucfirst($category->translation->title), ['index', 'category' => $category->translation->slug]);
            $output .= $btn;
            $output .= $dive;
            $output .= $uls;
            $output .= $children;
            $output .= $ule;
            $output .= '</li>';
        }
        if ($categories) {
            $output .= '';
        }
        return $output;
    }
}
