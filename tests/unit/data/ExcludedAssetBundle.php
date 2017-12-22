<?php
/**
 * ExcludedAssetBundle.php
 * @author Revin Roman
 * @link https://rmrevin.com
 */

namespace rmrevin\yii\minify\tests\unit\data;

use yii\web\AssetBundle;

/**
 * Class ExcludedAssetBundle
 * @package rmrevin\yii\minify\tests\unit\data
 */
class ExcludedAssetBundle extends AssetBundle
{

    public $css = [
        'excluded.css',
    ];

    public $js = [
        'excluded.js',
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/source';

        parent::init();
    }
}
