<?php
/**
 * JsMinifyTest.php
 * @author Dylan Ferris
 * @link https://github.com/acerix
 */

namespace rmrevin\yii\minify\tests\unit\view;

use rmrevin\yii\minify\components\JS;
use rmrevin\yii\minify\tests\unit\TestCase;

/**
 * Class JSTest
 * @package rmrevin\yii\minify\tests\unit\view
 */
class JSTest extends TestCase
{

    public function testRemoveJsComments()
    {

        $str = "
//remove comment
this1 //remove comment
this2 /* remove comment */
this3 /* remove
comment */
this4 /* * * remove
* * * *
comment * * */
this5 http://removecomment.com
id = id.replace(/\//g,''); //do not remove the regex //
HTTP+'//www.googleadservices.com/pagead/conversion'
";
        ;

        $this->assertEquals(
            "<div class=\" test\" data>\n<p>Inside text</p>\n<!-- comment -->\n<pre>    Inside pre\n    <span>test</span></pre>\n</div>",
            JS::removeJsComments($str)
        );

    }
}
