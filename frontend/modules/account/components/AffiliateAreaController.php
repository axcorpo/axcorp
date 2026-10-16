<?php

namespace frontend\modules\account\components;

use common\models\Affiliate;
use frontend\controllers\MainController;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;

/**
 * Base of the affiliate area controllers (docs/afiliere.md, "Contul afiliatului").
 *
 * Module::beforeAction() already keeps non-affiliates out; this repeats the check against
 * the `affiliate` row itself, because every query of these pages is scoped by its id —
 * an affiliate only ever sees their own data.
 *
 * Kept outside `controllers/` so the abstract class is not resolvable as a route.
 */
abstract class AffiliateAreaController extends MainController
{
	/**
	 * @var Affiliate|null
	 */
	private $_affiliate;

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
						'roles' => ['@'],
					],
				],
			],
		];
	}

	/**
	 * The signed-in user's active affiliate row.
	 *
	 * @return Affiliate
	 * @throws ForbiddenHttpException when the user is not an active affiliate
	 */
	public function getAffiliate()
	{
		if ($this->_affiliate === null) {
			$affiliate = Yii::$app->user->isGuest ? null : Affiliate::findByUserId(Yii::$app->user->id);
			if ($affiliate === null || !$affiliate->getIsActive()) {
				throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
			}
			$this->_affiliate = $affiliate;
		}
		return $this->_affiliate;
	}

	/**
	 * A status badge from a model's `getStatusLabels()`-shaped list.
	 *
	 * @param array $labels `[status => ['label' => ..., 'color' => ...]]`
	 * @param int|null $status
	 * @return string HTML
	 */
	public static function statusBadge(array $labels, $status)
	{
		if ($status === null || !isset($labels[(int) $status])) {
			return '&mdash;';
		}
		$color = $labels[(int) $status]['color'];
		// The models use Bootstrap 3 color names; "default" has no Bootstrap 5 badge.
		$color = $color === 'default' ? 'secondary' : $color;
		return Html::tag('span', Html::encode($labels[(int) $status]['label']), ['class' => "badge bg-{$color}"]);
	}

	/**
	 * The `<ul class="pagination">` options of the lists (Bootstrap 5 markup).
	 *
	 * @param \yii\data\Pagination $pagination
	 * @return array LinkPager config
	 */
	public static function pagerConfig($pagination)
	{
		return [
			'pagination' => $pagination,
			'maxButtonCount' => 5,
			'options' => ['class' => 'pagination justify-content-center mb-0'],
			'linkContainerOptions' => ['class' => 'page-item'],
			'linkOptions' => ['class' => 'page-link'],
			'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
			'prevPageLabel' => '&lsaquo;',
			'nextPageLabel' => '&rsaquo;',
		];
	}
}
