<?php
/**
 * CSSTest.php
 * @author Dylan Ferris
 * @link https://github.com/acerix
 */

namespace rmrevin\yii\minify\tests\unit\view;

use rmrevin\yii\minify\components\CSS;
use rmrevin\yii\minify\tests\unit\TestCase;

/**
 * Class CSSTest
 * @package rmrevin\yii\minify\tests\unit\view
 */
class CSSTest extends TestCase
{

    public function testConvertMediaTypeAttributeToMediaQuery()
    {

        $tests = [
            [
                'html' => '<link href="test.css" media="all" rel="stylesheet">',
                'content' => '.always{}',
                'result' => '.always{}'
            ],
            [
                'html' => '<link href="test.css" rel="stylesheet" media="print">',
                'content' => '.only_print{}',
                'result' => '@media print{.only_print{}}'
            ],
            [
                'html' => "<link media='screen' rel='stylesheet' href='test.css'>",
                'content' => '.only_screen{}',
                'result' => '@media screen{.only_screen{}}'
            ]
        ];
        
        foreach ($tests as $css_file) {
            $this->assertEquals(
                $css_file['result'],
                CSS::convertMediaTypeAttributeToMediaQuery($css_file['html'], $css_file['content'])
            );
        }
        

    }
}
