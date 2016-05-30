<?php
/**
 * CSS.php
 * @author Revin Roman
 * @link https://rmrevin.com
 */

namespace rmrevin\yii\minify\components;

use Yii;
use yii\helpers\Html;
use yii\helpers\StringHelper;

/**
 * Class CSS
 * @package rmrevin\yii\minify\components
 */
class CSS extends MinifyComponent
{

    public function minify()
    {
        $cssFiles = $this->view->cssFiles;

        $this->view->cssFiles = [];

        $toMinify = [];

        foreach ($cssFiles as $file => $html) {
            if ($this->thisFileNeedMinify($file, $html)) {
                $toMinify[$file] = $html;
            } else {
                if (!empty($toMinify)) {
                    $this->process($toMinify);

                    $toMinify = [];
                }

                $this->view->cssFiles[$file] = $html;
            }
        }

        if (!empty($toMinify)) {
            $this->process($toMinify);
        }

        unset($toMinify);
    }

    /**
     * @param array $files
     */
    protected function process(array $files)
    {
        $resultFile = $this->view->minify_path . '/' . $this->_getSummaryFilesHash($files) . '.css';

        if (!file_exists($resultFile)) {
            $css = '';

            foreach ($files as $file => $html) {
                $path = dirname($file);
                $file = $this->getAbsoluteFilePath($file);

                $content = file_get_contents($file);

                $result = [];

                preg_match_all('|url\(([^)]+)\)|is', $content, $m);
                if (!empty($m[0])) {
                    foreach ($m[0] as $k => $v) {
                        if (in_array(strpos($m[1][$k], 'data:'), [0, 1], true)) {
                            continue;
                        }

                        $url = str_replace(['\'', '"'], '', $m[1][$k]);

                        if ($this->isUrl($url)) {
                            $result[$m[1][$k]] = $url;
                        } else {
                            $result[$m[1][$k]] = $path . '/' . $url;
                        }
                    }

                    $content = strtr($content, $result);
                }

                $css .= $content;
            }

            $this->expandImports($css);

            $this->removeCssComments($css);

            $css = (new \CSSmin())
                ->run($css, $this->view->css_linebreak_pos);

            if (false !== $this->view->force_charset) {
                $charsets = '@charset "' . (string)$this->view->force_charset . '";' . "\n";
            } else {
                $charsets = $this->collectCharsets($css);
            }

            $imports = $this->collectImports($css);
            $fonts = $this->collectFonts($css);

            file_put_contents($resultFile, $charsets . $imports . $fonts . $css);

            if (false !== $this->view->file_mode) {
                @chmod($resultFile, $this->view->file_mode);
            }
        }

        $file = sprintf('%s%s', \Yii::getAlias($this->view->web_path), str_replace(\Yii::getAlias($this->view->base_path), '', $resultFile));

        $this->view->cssFiles[$file] = Html::cssFile($file);
    }

    /**
     * @param string $code
     */
    protected function removeCssComments(&$code)
    {
        if (true === $this->view->removeComments) {
            $code = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $code);
        }
    }

    /**
     * @param string $code
     */
    protected function expandImports(&$code)
    {
        if (true === $this->view->expand_imports) {
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
     * @param string $url
     * @return null|string
     */
    protected function _getImportContent($url)
    {
        $result = null;

        if ('url(' === StringHelper::byteSubstr($url, 0, 4)) {
            $url = str_replace(['url(\'', 'url("', 'url(', '\')', '")', ')'], '', $url);

            if (StringHelper::byteSubstr($url, 0, 2) === '//') {
                $url = preg_replace('|^//|', 'http://', $url, 1);
            }

            if (!empty($url)) {
                if (strpos($url, 'http://') !== 0) {
                    $url = Yii::getAlias($this->view->base_path . $url);
                }
                $result = file_get_contents($url);
            }
        }

        return $result;
    }
}