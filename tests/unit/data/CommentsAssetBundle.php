<?php
/**
 * CommentsAssetBundle.php.
 * @created 2017-12-22
 */

namespace rmrevin\yii\minify\tests\unit\data;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Class CommentsAssetBundle
 * @package rmrevin\yii\minify\tests\unit\data
 */
class CommentsAssetBundle extends AssetBundle
{

    public $js = [
        'comments.js',
    ];

    public $css = [
        'comments.css',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/source';

        parent::init();
    }
}
