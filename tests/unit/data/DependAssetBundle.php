<?php
/**
 * DependAssetBundle.php
 * @author Revin Roman http://phptime.ru
 */

namespace rmrevin\yii\minify\tests\unit\data;

use rmrevin\yii\minify\View;
use yii\web\AssetBundle;

class DependAssetBundle extends AssetBundle
{

    public $js = [
        'depend.js'
    ];

    public $css = [
        'depend.css'
    ];

    public $jsOptions = [
        'position' => View::POS_HEAD
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/source';
    }
}