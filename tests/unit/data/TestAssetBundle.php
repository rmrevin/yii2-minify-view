<?php
/**
 * TestAssetBundle.php
 * @author Revin Roman http://phptime.ru
 */

namespace rmrevin\yii\minify\tests\unit\data;

/**
 * Class TestAssetBundle
 * @package rmrevin\yii\minify\tests\unit\data
 */
class TestAssetBundle extends \yii\web\AssetBundle
{

    public $js = [
        'test.js'
    ];

    public $css = [
        'test.css'
    ];

    public $depends = [
        'rmrevin\yii\minify\tests\unit\data\DependAssetBundle'
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/source';
    }
}