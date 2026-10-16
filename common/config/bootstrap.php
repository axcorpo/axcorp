<?php
Yii::$classMap['yii\helpers\Html'] = '@common/helpers/Html.php';

Yii::setAlias('@base', dirname(__DIR__, 3));
Yii::setAlias('@root', dirname(__DIR__, 2));
Yii::setAlias('@common', dirname(__DIR__));
Yii::setAlias('@frontend', dirname(__DIR__, 2) . '/frontend');
Yii::setAlias('@backend', dirname(__DIR__, 2) . '/backend');
Yii::setAlias('@console', dirname(__DIR__, 2) . '/console');
Yii::setAlias('@uploads', dirname(__DIR__, 2) . '/uploads');
Yii::setAlias('@backups', dirname(__DIR__, 2) . '/_backups');

Yii::setAlias('@workspace', dirname(__DIR__, 3) . '/workspace');
Yii::setAlias('@documentation', dirname(__DIR__, 3) . '/documentation');
