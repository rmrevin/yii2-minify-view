<?php
/**
 * View.php
 * @author Revin Roman http://phptime.ru
 */

namespace rmrevin\yii\minify;

use yii\helpers;

/**
 * Class View
 * @package rmrevin\yii\minify
 */
class View extends \yii\web\View
{

    /**
     * @var string path alias to web base
     */
    public $base_path = '@app/web';

    /**
     * @var string path alias to save minify result
     */
    public $minify_path = '@app/web/minify';

    /**
     * @var array positions of js files to be minified
     */
    public $js_position = [self::POS_END, self::POS_HEAD];

    /**
     * @var bool|string charset forcibly assign, otherwise will use all of the files found charset
     */
    public $force_charset = false;

    /**
     * @var bool whether to change @import on content
     */
    public $expand_imports = true;

    /**
     * @var int
     */
    public $css_linebreak_pos = 2048;

    /**
     * @var int|bool chmod of minified file. If false chmod not set
     */
    public $file_mode = 0664;

    /**
     * @var array schemes that will be ignored during normalization url
     */
    public $schemas = ['//', 'http://', 'https://', 'ftp://'];

    /**
     * @var bool do I need to compress the result html page.
     */
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
                    $Response->data = HtmlCompressor::compress($Response->data, ['extra' => true]);
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

        $this->minify();

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
     * @inheritdoc
     */
    protected function registerAssetFiles($name)
    {
        if (!isset($this->assetBundles[$name])) {
            return;
        }

        $bundle = $this->assetBundles[$name];
        if ($bundle) {
            foreach ($bundle->depends as $dep) {
                $this->registerAssetFiles($dep);
            }

            $bundle->registerAssetFiles($this);
        }

        unset($this->assetBundles[$name]);
    }

    private function minify()
    {
        $this
            ->minifyCSS()
            ->minifyJS();
    }

    /**
     * @return self
     */
    protected function minifyCSS()
    {
        if (!empty($this->cssFiles)) {
            $css_files = array_keys($this->cssFiles);

            $css_minify_file = $this->minify_path . '/' . $this->_getSummaryFilesHash($this->cssFiles) . '.css';
            if (!file_exists($css_minify_file)) {
                $css = '';

                foreach ($css_files as $file) {
                    $content = file_get_contents(\Yii::getAlias($this->base_path) . $file);

                    preg_match_all('|url\(([^)]+)\)|is', $content, $m);
                    if (!empty($m[0])) {
                        $path = dirname($file);
                        $result = [];
                        foreach ($m[0] as $k => $v) {
                            if (0 === strpos($m[1][$k], 'data:')) {
                                continue;
                            }
                            $url = str_replace(['\'', '"'], '', $m[1][$k]);
                            if (preg_match('#^(' . implode('|', $this->schemas) . ')#is', $url)) {
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

                $css = (new \CSSmin())
                    ->run($css, $this->css_linebreak_pos);

                if (false !== $this->force_charset) {
                    $charsets = '@charset "' . (string)$this->force_charset . '";' . "\n";
                } else {
                    $charsets = $this->collectCharsets($css);
                }

                $imports = $this->collectImports($css);
                $fonts = $this->collectFonts($css);

                file_put_contents($css_minify_file, $charsets . $imports . $fonts . $css);
                if (false !== $this->file_mode) {
                    chmod($css_minify_file, $this->file_mode);
                }
            }

            $css_file = str_replace(\Yii::getAlias($this->base_path), '', $css_minify_file);
            $this->cssFiles = [$css_file => helpers\Html::cssFile($css_file)];
        }

        return $this;
    }

    /**
     * @param string $css
     */
    private function expandImports(&$css)
    {
        if (true === $this->expand_imports) {
            preg_match_all('|\@import\s([^;]+);|is', $css, $m);
            if (!empty($m[0])) {
                foreach ($m[0] as $k => $v) {
                    $import_url = $m[1][$k];
                    if (!empty($import_url)) {
                        $import_content = $this->getImportContent($import_url);
                        if (!empty($import_content)) {
                            $css = str_replace($m[0][$k], $import_content, $css);
                        }
                    }
                }
            }
        }
    }

    private function collectCharsets(&$css)
    {
        $charsets = '';

        foreach ($this->_getAllPattern('|\@charset[^;]+|is', $css) as $string) {
            $string = $string . ';';
            $css = str_replace($string, '', $css);
            if (false === $this->force_charset) {
                $charsets .= $string . PHP_EOL;
            }
        }

        return $charsets;
    }

    private function collectImports(&$css)
    {
        $imports = '';

        foreach ($this->_getAllPattern('|\@import[^;]+|is', $css) as $string) {
            $string = $string . ';';
            $imports .= $string . PHP_EOL;
            $css = str_replace($string, '', $css);
        }

        return $imports;
    }

    private function collectFonts(&$css)
    {
        $fonts = '';

        foreach ($this->_getAllPattern('|\@font-face\{[^}]+\}|is', $css) as $string) {
            $fonts .= $string . PHP_EOL;
            $css = str_replace($string, '', $css);
        }

        return $fonts;
    }

    /**
     * @return self
     */
    protected function minifyJS()
    {
        if (!empty($this->jsFiles)) {
            $js_files = $this->jsFiles;
            foreach ($js_files as $position => $files) {
                if (false === in_array($position, $this->js_position)) {
                    $this->jsFiles[$position] = [];
                    foreach ($files as $file => $html) {
                        $this->jsFiles[$position][$file] = helpers\Html::jsFile($file);
                    }
                } else {
                    $this->jsFiles[$position] = [];

                    $js_minify_file = $this->minify_path . '/' . $this->_getSummaryFilesHash($files) . '.js';
                    if (!file_exists($js_minify_file)) {
                        $js = '';
                        foreach ($files as $file => $html) {
                            $file = \Yii::getAlias($this->base_path) . $file;
                            $js .= file_get_contents($file) . ';' . PHP_EOL;
                        }

                        $js = (new \JSMin($js))
                            ->min();

                        file_put_contents($js_minify_file, $js);
                        if (false !== $this->file_mode) {
                            chmod($js_minify_file, $this->file_mode);
                        }
                    }

                    $js_file = str_replace(\Yii::getAlias($this->base_path), '', $js_minify_file);
                    $this->jsFiles[$position][$js_file] = helpers\Html::jsFile($js_file);
                }
            }
        }

        return $this;
    }

    /**
     * @param string $url
     * @return null|string
     */
    private function getImportContent($url)
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
     * @param string $pattern
     * @param string $css
     * @return array
     */
    private function _getAllPattern($pattern, $css)
    {
        preg_match_all($pattern, $css, $m);

        return $m[0];
    }

    /**
     * @param array $files
     * @return string
     */
    private function _getSummaryFilesHash($files)
    {
        $result = '';
        foreach ($files as $file => $html) {
            $result .= sha1_file(\Yii::getAlias($this->base_path) . $file);
        }

        return sha1($result);
    }
}
