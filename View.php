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
    public $compressCss = true;

    /** @var bool */
    public $minifyJs = true;

    /** @var bool */
    public $compressJs = true;

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
                        $Response->data = HtmlCompressor::compress($Response->data, ['extra' => true]);
                    }

                    if (!empty($Response->content)) {
                        $Response->content = HtmlCompressor::compress($Response->content, ['extra' => true]);
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
                $file = str_replace(\Yii::getAlias($this->web_path), '', $file);

                $content = file_get_contents(\Yii::getAlias($this->base_path) . $file);

                preg_match_all('|url\(([^)]+)\)|is', $content, $m);
                if (!empty($m[0])) {
                    $path = dirname($file);
                    $result = [];
                    foreach ($m[0] as $k => $v) {
                        if (in_array(strpos($m[1][$k], 'data:'), [0, 1], true)) {
                            continue;
                        }
                        $url = str_replace(['\'', '"'], '', $m[1][$k]);
                        if ($this->isUrl($url)) {
                            $result[$m[1][$k]] = '\'' . $url . '\'';
                        } else {
                            $result[$m[1][$k]] = '\'' . \Yii::getAlias($this->web_path) . $path . '/' . $url . '\'';
                        }
                    }
                    $content = str_replace(array_keys($result), array_values($result), $content);
                }

                $css .= $content;
            }

            $this->expandImports($css);

            if ($this->compressCss) {
                $css = (new \CSSmin())
                    ->run($css, $this->css_linebreak_pos);
            }

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
                $file = \Yii::getAlias($this->base_path) . str_replace(\Yii::getAlias($this->web_path), '', $file);
                $js .= file_get_contents($file) . ';' . PHP_EOL;
            }

            if ($this->compressJs) {
                $js = (new \JSMin($js))
                    ->min();
            }

            file_put_contents($resultFile, $js);

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
     * @param string $css
     * @return string
     */
    protected function collectCharsets(&$css)
    {
        return $this->_collect($css, '|\@charset[^;]+|is', function ($string) {
            return $string . ';';
        });
    }

    /**
     * @param string $css
     * @return string
     */
    protected function collectImports(&$css)
    {
        return $this->_collect($css, '|\@import[^;]+|is', function ($string) {
            return $string . ';';
        });
    }

    /**
     * @param string $css
     * @return string
     */
    protected function collectFonts(&$css)
    {
        return $this->_collect($css, '|\@font-face\{[^}]+\}|is', function ($string) {
            return $string;
        });
    }

    /**
     * @param string $css
     */
    protected function expandImports(&$css)
    {
        if (true === $this->expand_imports) {
            preg_match_all('|\@import\s([^;]+);|is', str_replace('&amp;', '&', $css), $m);
            if (!empty($m[0])) {
                foreach ($m[0] as $k => $v) {
                    $import_url = $m[1][$k];
                    if (!empty($import_url)) {
                        $import_content = $this->_getImportContent($import_url);
                        if (!empty($import_content)) {
                            $css = str_replace($m[0][$k], $import_content, $css);
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
     * @param string $css
     * @param string $pattern
     * @param callable $handler
     * @return string
     */
    protected function _collect(&$css, $pattern, $handler)
    {
        $result = '';

        preg_match_all($pattern, $css, $m);
        foreach ($m[0] as $string) {
            $string = $handler($string);
            $css = str_replace($string, '', $css);

            $result .= $string . PHP_EOL;
        }

        return $result;
    }

    /**
     * @param array $files
     * @return string
     */
    protected function _getSummaryFilesHash($files)
    {
        $result = '';
        foreach ($files as $file => $html) {
            $path = \Yii::getAlias($this->base_path) . $file;

            if ($this->thisFileNeedMinify($file, $html) && file_exists($path)) {
                $result .= $path . '?' . filemtime($path);
            }
        }

        return sha1($result);
    }
}
