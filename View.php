<?php
/**
 * View.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify;

use yii\helpers;

/**
 * Class View
 * @package rmrevin\yii\minify
 */
class View extends \yii\web\View
{

    /** @var bool */
    public $enableMinify = true;

    /** @var bool */
    public $minifyCss = true;

    /** @var bool */
    public $minifyJs = true;

    /** @var bool */
    public $removeComments = true;

    /** @var string path alias to web base (in url) */
    public $web_path = '@web';

    /** @var string path alias to web base (absolute) */
    public $base_path = '@webroot';

    /** @var string path alias to save minify result */
    public $minify_path = '@webroot/minify';

    /** @var array positions of js files to be minified */
    public $js_position = [self::POS_END, self::POS_HEAD];

    /** @var bool|string charset forcibly assign, otherwise will use all of the files found charset */
    public $force_charset = false;

    /** @var bool whether to change @import on content */
    public $expand_imports = true;

    /** @var int */
    public $css_linebreak_pos = 2048;

    /** @var int|bool chmod of minified file. If false chmod not set */
    public $file_mode = 0664;

    /** @var array schemes that will be ignored during normalization url */
    public $schemas = ['//', 'http://', 'https://', 'ftp://'];

    /** @var bool do I need to compress the result html page. */
    public $compress_output = false;

    /**
     * @var array options for compressing output result
     *   * extra - use more compact algorithm
     *   * no-comments - cut all the html comments
     */
    public $compress_options = ['extra' => true];

    /**
     * @throws \rmrevin\yii\minify\Exception
     */
    public function init()
    {
        parent::init();

        $minify_path = $this->minify_path = (string)\Yii::getAlias($this->minify_path);
        if (!file_exists($minify_path)) {
            helpers\FileHelper::createDirectory($minify_path);
        }

        if (!is_readable($minify_path)) {
            throw new Exception('Directory for compressed assets is not readable.');
        }

        if (!is_writable($minify_path)) {
            throw new Exception('Directory for compressed assets is not writable.');
        }

        if (true === $this->compress_output) {
            \Yii::$app->response->on(\yii\web\Response::EVENT_BEFORE_SEND, function (\yii\base\Event $Event) {
                /** @var \yii\web\Response $Response */
                $Response = $Event->sender;
                if ($Response->format === \yii\web\Response::FORMAT_HTML) {
                    if (!empty($Response->data)) {
                        $Response->data = HtmlCompressor::compress($Response->data, $this->compress_options);
                    }

                    if (!empty($Response->content)) {
                        $Response->content = HtmlCompressor::compress($Response->content, $this->compress_options);
                    }
                }
            });
        }
    }

    /**
     * @inheritdoc
     */
    public function endPage($ajaxMode = false)
    {
        $this->trigger(self::EVENT_END_PAGE);

        $content = ob_get_clean();
        foreach (array_keys($this->assetBundles) as $bundle) {
            $this->registerAssetFiles($bundle);
        }

        if (true === $this->enableMinify) {
            if (true === $this->minifyCss) {
                $this->minifyCSS();
            }

            if (true === $this->minifyJs) {
                $this->minifyJS();
            }
        }

        echo strtr(
            $content,
            [
                self::PH_HEAD => $this->renderHeadHtml(),
                self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
                self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
            ]
        );

        $this->clear();
    }

    /**
     * @return self
     */
    protected function minifyCSS()
    {
        if (!empty($this->cssFiles)) {
            $cssFiles = $this->cssFiles;

            $this->cssFiles = [];

            $toMinify = [];

            foreach ($cssFiles as $file => $html) {
                if ($this->thisFileNeedMinify($file, $html)) {
                    $toMinify[$file] = $html;
                } else {
                    if (!empty($toMinify)) {
                        $this->processMinifyCss($toMinify);

                        $toMinify = [];
                    }

                    $this->cssFiles[$file] = $html;
                }
            }

            if (!empty($toMinify)) {
                $this->processMinifyCss($toMinify);
            }

            unset($toMinify);
        }

        return $this;
    }

    /**
     * @param array $files
     */
    protected function processMinifyCss($files)
    {
        $resultFile = $this->minify_path . '/' . $this->_getSummaryFilesHash($files) . '.css';

        if (!file_exists($resultFile)) {
            $css = '';

            foreach ($files as $file => $html) {
                $path = dirname($file);
                $file = $this->getAbsoluteFilePath($file);

                $content = file_get_contents($file);

                preg_match_all('|url\(([^)]+)\)|is', $content, $m);
                if (!empty($m[0])) {
                    $result = [];

                    foreach ($m[0] as $k => $v) {
                        if (in_array(strpos($m[1][$k], 'data:'), [0, 1], true)) {
                            continue;
                        }

                        $url = str_replace(['\'', '"'], '', $m[1][$k]);

                        if ($this->isUrl($url)) {
                            $result[$m[1][$k]] = '\'' . $url . '\'';
                        } else {
                            $result[$m[1][$k]] = '\'' . $path . '/' . $url . '\'';
                        }
                    }

                    $content = str_replace(array_keys($result), array_values($result), $content);
                }

                $css .= $content;
            }

            $this->expandImports($css);

            $this->removeCssComments($css);

            $css = (new \CSSmin())
                ->run($css, $this->css_linebreak_pos);

            if (false !== $this->force_charset) {
                $charsets = '@charset "' . (string)$this->force_charset . '";' . "\n";
            } else {
                $charsets = $this->collectCharsets($css);
            }

            $imports = $this->collectImports($css);
            $fonts = $this->collectFonts($css);

            file_put_contents($resultFile, $charsets . $imports . $fonts . $css);

            if (false !== $this->file_mode) {
                @chmod($resultFile, $this->file_mode);
            }
        }

        $file = sprintf('%s%s', \Yii::getAlias($this->web_path), str_replace(\Yii::getAlias($this->base_path), '', $resultFile));

        $this->cssFiles[$file] = helpers\Html::cssFile($file);
    }

    /**
     * @return self
     */
    protected function minifyJS()
    {
        if (!empty($this->jsFiles)) {
            $jsFiles = $this->jsFiles;

            foreach ($jsFiles as $position => $files) {
                if (false === in_array($position, $this->js_position, true)) {
                    $this->jsFiles[$position] = [];
                    foreach ($files as $file => $html) {
                        $this->jsFiles[$position][$file] = $html;
                    }
                } else {
                    $this->jsFiles[$position] = [];

                    $toMinify = [];

                    foreach ($files as $file => $html) {
                        if ($this->thisFileNeedMinify($file, $html)) {
                            $toMinify[$file] = $html;
                        } else {
                            if (!empty($toMinify)) {
                                $this->processMinifyJs($position, $toMinify);

                                $toMinify = [];
                            }

                            $this->jsFiles[$position][$file] = $html;
                        }
                    }

                    if (!empty($toMinify)) {
                        $this->processMinifyJs($position, $toMinify);
                    }

                    unset($toMinify);
                }
            }
        }

        return $this;
    }

    /**
     * @param integer $position
     * @param array $files
     */
    protected function processMinifyJs($position, $files)
    {
        $resultFile = sprintf('%s/%s.js', $this->minify_path, $this->_getSummaryFilesHash($files));
        if (!file_exists($resultFile)) {
            $js = '';
            foreach ($files as $file => $html) {
                $file = $this->getAbsoluteFilePath($file);
                $js .= file_get_contents($file) . ';' . PHP_EOL;
            }

            $this->removeJsComments($js);

            $compressedJs = (new \JSMin($js))
                ->min();

            file_put_contents($resultFile, $compressedJs);

            if (false !== $this->file_mode) {
                @chmod($resultFile, $this->file_mode);
            }
        }

        $file = sprintf('%s%s', \Yii::getAlias($this->web_path), str_replace(\Yii::getAlias($this->base_path), '', $resultFile));

        $this->jsFiles[$position][$file] = helpers\Html::jsFile($file);
    }

    /**
     * @param string $url
     * @param boolean $checkSlash
     * @return bool
     */
    protected function isUrl($url, $checkSlash = true)
    {
        $regexp = '#^(' . implode('|', $this->schemas) . ')#is';
        if ($checkSlash) {
            $regexp = '#^(/|\\\\|' . implode('|', $this->schemas) . ')#is';
        }

        return (bool)preg_match($regexp, $url);
    }

    /**
     * @param string $string
     * @return bool
     */
    protected function isContainsConditionalComment($string)
    {
        return strpos($string, '<![endif]-->') !== false;
    }

    /**
     * @param string $file
     * @param string $html
     * @return bool
     */
    protected function thisFileNeedMinify($file, $html)
    {
        return !$this->isUrl($file, false) && !$this->isContainsConditionalComment($html);
    }

    /**
     * @param string $code
     * @return string
     */
    protected function collectCharsets(&$code)
    {
        return $this->_collect($code, '|\@charset[^;]+|is', function ($string) {
            return $string . ';';
        });
    }

    /**
     * @param string $code
     * @return string
     */
    protected function collectImports(&$code)
    {
        return $this->_collect($code, '|\@import[^;]+|is', function ($string) {
            return $string . ';';
        });
    }

    /**
     * @param string $code
     * @return string
     */
    protected function collectFonts(&$code)
    {
        return $this->_collect($code, '|\@font-face\{[^}]+\}|is', function ($string) {
            return $string;
        });
    }

    /**
     * @param string $code
     */
    protected function removeCssComments(&$code)
    {
        if (true === $this->removeComments) {
            $code = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $code);
        }
    }

    /**
     * @param string $code
     */
    protected function removeJsComments(&$code)
    {
        // @todo
        if (true === $this->removeComments) {
            //$code = preg_replace('', '', $code);
        }
    }

    /**
     * @param string $code
     */
    protected function expandImports(&$code)
    {
        if (true === $this->expand_imports) {
            preg_match_all('|\@import\s([^;]+);|is', str_replace('&amp;', '&', $code), $m);
            if (!empty($m[0])) {
                foreach ($m[0] as $k => $v) {
                    $import_url = $m[1][$k];
                    if (!empty($import_url)) {
                        $import_content = $this->_getImportContent($import_url);
                        if (!empty($import_content)) {
                            $code = str_replace($m[0][$k], $import_content, $code);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $url
     * @return null|string
     */
    protected function _getImportContent($url)
    {
        $result = null;

        if ('url(' === helpers\StringHelper::byteSubstr($url, 0, 4)) {
            $url = str_replace(['url(\'', 'url("', 'url(', '\')', '")', ')'], '', $url);

            if (helpers\StringHelper::byteSubstr($url, 0, 2) === '//') {
                $url = preg_replace('|^//|', 'http://', $url, 1);
            }

            if (!empty($url)) {
                $result = file_get_contents($url);
            }
        }

        return $result;
    }

    /**
     * @param string $code
     * @param string $pattern
     * @param callable $handler
     * @return string
     */
    protected function _collect(&$code, $pattern, $handler)
    {
        $result = '';

        preg_match_all($pattern, $code, $m);
        foreach ($m[0] as $string) {
            $string = $handler($string);
            $code = str_replace($string, '', $code);

            $result .= $string . PHP_EOL;
        }

        return $result;
    }

    /**
     * @param string $file
     * @return string
     */
    protected function cleanFileName($file)
    {
        return (strpos($file, '?')) ? parse_url($file, PHP_URL_PATH) : $file;
    }

    /**
     * @param string $file
     * @return string
     */
    protected function getAbsoluteFilePath($file)
    {
        return \Yii::getAlias($this->base_path) . str_replace(\Yii::getAlias($this->web_path), '', $this->cleanFileName($file));
    }

    /**
     * @param array $files
     * @return string
     */
    protected function _getSummaryFilesHash($files)
    {
        $result = '';
        foreach ($files as $file => $html) {
            $path = $this->getAbsoluteFilePath($file);

            if ($this->thisFileNeedMinify($file, $html) && file_exists($path)) {
                $result .= sha1_file($path);
            }
        }

        return sha1($result);
    }
}