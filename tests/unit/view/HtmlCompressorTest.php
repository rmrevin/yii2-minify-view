<?php
/**
 * HtmlCompressorTest.php
 * @author Revin Roman http://phptime.ru
 */

namespace rmrevin\yii\minify\tests\unit\view;

use rmrevin\yii\minify\HtmlCompressor;
use rmrevin\yii\minify\tests\unit\TestCase;

/**
 * Class HtmlCompressorTest
 * @package rmrevin\yii\minify\tests\unit\view
 */
class HtmlCompressorTest extends TestCase
{

    public function testMain()
    {
        $str = '<div class="                   test"                  data>
            <p>Inside text</p>
            <!-- comment -->
            <pre>test<span></span></pre>
        </div>';

        $this->assertEquals(
            "<div class=\" test\" data>\n<p>Inside text</p>\n<!-- comment -->\n<pre>test<span></span></pre>\n</div>",
            HtmlCompressor::compress($str)
        );
        $this->assertEquals(
            "<div class=\" test\" data>\n<p>Inside text</p>\n\n<pre>test<span></span></pre>\n</div>",
            HtmlCompressor::compress($str, ['no-comments' => true])
        );
        $this->assertEquals(
            "<div class=\" test\" data><p>Inside text</p><!-- comment --><pre>test<span></span></pre></div>",
            HtmlCompressor::compress($str, ['extra' => true])
        );
        $this->assertEquals(
            "<div class=\" test\" data><p>Inside text</p><pre>test<span></span></pre></div>",
            HtmlCompressor::compress($str, ['no-comments' => true, 'extra' => true])
        );

        $this->expectOutputString('Original Size: 176
Compressed Size: 96
Savings: 45.45%
');
        HtmlCompressor::compress($str, ['stats' => true]);
    }
}