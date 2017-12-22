<?php
/**
 * main.php
 * @author Roman Revin http://phptime.ru
 */

define('BASE_PATH', dirname(__DIR__));

return [
    'id'         => 'testapp',
    'basePath'   => BASE_PATH,
    'components' => [
        'view'         => [
            'class'        => 'rmrevin\yii\minify\View',
            'minifyPath'   => BASE_PATH . '/runtime/minyfy',
            'basePath'     => BASE_PATH . '/runtime',
            'webPath'      => '/runtime',
            'forceCharset' => 'CP1251',
            'cache'        => 'cache',
        ],
        'assetManager' => [
            'basePath' => BASE_PATH . '/runtime/assets',
            'baseUrl'  => '/assets',
        ],
        'cache'        => [
            'class' => 'yii\caching\FileCache',
        ],
    ],
];
