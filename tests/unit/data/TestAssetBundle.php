<?php
/**
 * TestAssetBundle.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify\tests\unit\data;

/**
 * Class TestAssetBundle
 * @package rmrevin\yii\minify\tests\unit\data
 */
class TestAssetBundle extends \yii\web\AssetBundle
{

    public $sourcePath = __DIR__ . '/source';

    public $js = [
        'test.js',
    ];

    public $css = [
        'test.css',
    ];

    public $jsOptions = [
        'position' => \rmrevin\yii\minify\View::POS_READY,
    ];

    public $depends = [
        'rmrevin\yii\minify\tests\unit\data\DependAssetBundle',
    ];
}
