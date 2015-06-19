<?php
/**
 * ViewTest.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify\tests\unit\view;

use rmrevin\yii\minify;
use yii\helpers\FileHelper;

/**
 * Class ViewTest
 * @package rmrevin\yii\minify\tests\unit\view
 */
class ViewTest extends minify\tests\unit\TestCase
{

    public function testMain()
    {
        $this->assertInstanceOf('rmrevin\yii\minify\View', $this->getView());

        $this->assertEquals('CP1251', $this->getView()->force_charset);
    }

    public function testEndPage()
    {
        minify\tests\unit\data\TestAssetBundle::register($this->getView());

        ob_start();
        echo '<html>This is test page</html>';
        $this->getView()->endPage(false);

        $files = FileHelper::findFiles($this->getView()->minify_path);
        $this->assertEquals(2, count($files));

        foreach ($files as $file) {
            $this->assertNotEmpty(file_get_contents($file));
        }
    }

    public function testAlternativeEndPage()
    {
        $this->getView()->expand_imports = false;
        $this->getView()->force_charset = false;

        minify\tests\unit\data\TestAssetBundle::register($this->getView());

        ob_start();
        echo '<html>This is test page</html>';
        $this->getView()->endPage(false);
    }

    public function testMinifyPathReadableException()
    {
        $this->setExpectedException('rmrevin\yii\minify\Exception', 'Directory for compressed assets is not readable.');

        \Yii::createObject([
            'class' => 'rmrevin\yii\minify\View',
            'minify_path' => '/root',
        ]);
    }

    public function testMinifyPathWritableException()
    {
        $this->setExpectedException('rmrevin\yii\minify\Exception', 'Directory for compressed assets is not writable.');

        \Yii::createObject([
            'class' => 'rmrevin\yii\minify\View',
            'minify_path' => '/etc',
        ]);
    }

    /**
     * @return minify\View
     */
    private function getView()
    {
        return \Yii::$app->getView();
    }
}