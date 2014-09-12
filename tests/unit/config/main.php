<?php
/**
 * main.php
 * @author Roman Revin http://phptime.ru
 */

return [
    'id' => 'testapp',
    'basePath' => realpath(__DIR__ . '/..'),
    'components' => [
        'view' => [
            'class' => rmrevin\yii\minify\View::className(),
        ]
    ],
];