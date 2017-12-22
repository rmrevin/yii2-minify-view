<?php
/**
 * TestAssetBundle.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify\tests\unit\data;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Class TestAssetBundle
 * @package rmrevin\yii\minify\tests\unit\data
 */
class TestAssetBundle extends AssetBundle
{

    public $js = [
        'test.js',
    ];

    public $css = [
        'test.css',
    ];

    public $jsOptions = [
        'position' => View::POS_READY,
    ];

    public $cssOptions = [
        'media' => 'all',
    ];

    public $depends = [
        'rmrevin\yii\minify\tests\unit\data\DependAssetBundle',
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/source';

        parent::init();
    }
}
