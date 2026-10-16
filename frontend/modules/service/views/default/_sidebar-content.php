<?php
/* @var $this yii\web\View */

use yii\helpers\Html;
use yii\helpers\Url;
?>

<?php if ($serviceCategories = \common\models\ServiceCategory::findAllServiceCategories()): ?>
	<nav class="wg-siderbar-category service-categories">
		<?php foreach ($serviceCategories as $serviceCategory): ?>
			<?php $serviceCategoryTranslation = $serviceCategory->getTranslation(); ?>
			<a class="siderbar-category-item text-title-1 lh-100 fw-6 letter-space--3 <?= Yii::$app->request->get('category') == $serviceCategoryTranslation->slug ? 'active' : '' ?>" href="<?= Url::to(['index', 'category' => $serviceCategoryTranslation->slug]) ?>">
				<span class="icon-arrow-up-right"></span>
				<span class="text-title"><?= $serviceCategoryTranslation->title ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>
