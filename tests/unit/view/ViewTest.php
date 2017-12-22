<?php
/**
 * ViewTest.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify\tests\unit\view;

use rmrevin\yii\minify;
use yii\helpers\FileHelper;
use yii\helpers\Url;

/**
 * Class ViewTest
 * @package rmrevin\yii\minify\tests\unit\view
 */
class ViewTest extends minify\tests\unit\TestCase
{

    public function testMain()
    {
        $this->assertInstanceOf('rmrevin\yii\minify\View', $this->getView());

        $this->assertEquals('CP1251', $this->getView()->forceCharset);
    }

    public function testEmptyBundle()
    {
        $view = $this->getView();

        minify\tests\unit\data\EmptyAssetBundle::register($this->getView());

        ob_start();
        echo '<html>This is test page</html>';

        $view->endBody();
        $view->endPage();

        $files = FileHelper::findFiles($this->getView()->minifyPath);

        $this->assertCount(0, $files);

        foreach ($files as $file) {
            $this->assertNotEmpty(file_get_contents($file));
        }
    }

    public function testEndPage()
    {
        $view = $this->getView();

        minify\tests\unit\data\TestAssetBundle::register($this->getView());

        ob_start();
        echo '<html>This is test page</html>';

        $view->endBody();
        $view->endPage();

        $files = FileHelper::findFiles($this->getView()->minifyPath);

        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertNotEmpty(file_get_contents($file));
        }
    }

    public function testRemoveComments()
    {
        $view = $this->getView();

        minify\tests\unit\data\CommentsAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $view->endPage();

        $files = FileHelper::findFiles($this->getView()->minifyPath);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if (false !== mb_strpos($file, '.js')) {
                $this->assertEquals("this1
this2
this3
this4
this5 http:id=id.replace(/\\//g,'');HTTP+'//www.googleadservices.com/pagead/conversion';", $content);
            }

            if (false !== mb_strpos($file, '.css')) {
                $this->assertEquals('@charset "CP1251";
div.test{border:1px solid #000;width:100%;height:100%}', $content);
            }
        }
    }

    public function testMediaPrint()
    {
        $view = $this->getView();

        minify\tests\unit\data\PrintAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $view->endPage();

        $files = FileHelper::findFiles($this->getView()->minifyPath);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            $this->assertEquals('@charset "CP1251";
@media print{.print-hide{display:none}.bad>div>.formatting{color:gray}}', $content);
        }
    }

    public function testMainWithVersion()
    {
        $view = $this->getView();
        $view->assetManager->appendTimestamp = true;

        $this->assertInstanceOf(minify\View::className(), $view);

        $this->assertEquals('CP1251', $view->forceCharset);
    }

    public function testEndPageWithVersion()
    {
        $view = $this->getView();
        $view->assetManager->appendTimestamp = true;

        minify\tests\unit\data\TestAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page with versioning</html>';

        $view->endBody();

        $this->assertCount(2, $view->jsFiles[minify\View::POS_HEAD]);
        $this->assertCount(1, $view->jsFiles[minify\View::POS_READY]);

        $view->endPage();

        $files = FileHelper::findFiles($view->minifyPath);

        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertNotEmpty(file_get_contents($file));
        }
    }

    public function testAlternativeEndPageWithVersion()
    {
        $view = $this->getView();
        $view->assetManager->appendTimestamp = true;

        $view->expandImports = false;
        $view->forceCharset = false;

        minify\tests\unit\data\TestAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $this->assertCount(2, $view->jsFiles[minify\View::POS_HEAD]);
        $this->assertCount(1, $view->jsFiles[minify\View::POS_READY]);

        foreach ((array)$view->jsFiles[minify\View::POS_HEAD] as $file => $html) {
            if (Url::isRelative($file)) {
                $this->assertNotFalse(mb_strpos($file, '?v='));
            }
        }

        foreach ((array)$view->jsFiles[minify\View::POS_READY] as $file => $html) {
            if (Url::isRelative($file)) {
                $this->assertNotFalse(mb_strpos($file, '?v='));
            }
        }

        $view->endPage();
    }

    public function testFiletimeCheckAlgorithm()
    {
        $view = $this->getView();
        $view->fileCheckAlgorithm = 'filemtime';

        minify\tests\unit\data\TestAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $this->assertCount(2, $view->jsFiles[minify\View::POS_HEAD]);
        $this->assertCount(1, $view->jsFiles[minify\View::POS_READY]);

        $view->endPage();
    }

    public function testExcludeBundles()
    {
        $view = $this->getView();
        $view->excludeBundles = [
            minify\tests\unit\data\ExcludedAssetBundle::className(),
        ];

        $view->init();

        minify\tests\unit\data\TestAssetBundle::register($view);
        minify\tests\unit\data\ExcludedAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $this->assertCount(2, $view->cssFiles);
        $this->assertCount(3, $view->jsFiles);

        $view->endPage();
    }

    public function testExcludeFiles()
    {
        $view = $this->getView();
        $view->excludeFiles = [
            'excluded.css',
        ];

        minify\tests\unit\data\TestAssetBundle::register($view);
        minify\tests\unit\data\ExcludedAssetBundle::register($view);

        ob_start();
        echo '<html>This is test page versioning</html>';

        $view->endBody();

        $this->assertCount(2, $view->cssFiles);
        $this->assertCount(3, $view->jsFiles);

        $view->endPage();
    }

    /**
     * @return minify\View
     */
    protected function getView()
    {
        return \Yii::$app->getView();
    }
}
