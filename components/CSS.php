<?php
/**
 * CSS.php
 * @author Revin Roman
 * @link https://rmrevin.com
 */

namespace rmrevin\yii\minify\components;

use yii\helpers\Html;

/**
 * Class CSS
 * @package rmrevin\yii\minify\components
 */
class CSS extends MinifyComponent
{

    public function export()
    {
        $cssFiles = $this->view->cssFiles;

        $this->view->cssFiles = [];

        $toMinify = [];

        foreach ($cssFiles as $file => $html) {
            if ($this->thisFileNeedMinify($file, $html)) {
                if ($this->view->concatCss) {
                    $toMinify[$file] = $html;
                } else {
                    $this->process([$file => $html]);
                }
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
        $minifyPath = $this->view->minifyPath;
        $hash = $this->_getSummaryFilesHash($files);

        $resultFile = $minifyPath . DIRECTORY_SEPARATOR . $hash . '.css';

        if (!file_exists($resultFile)) {
            $css = '';

            foreach ($files as $file => $html) {
                $cacheKey = $this->buildCacheKey($file);

                $content = $this->getFromCache($cacheKey);

                if (false !== $content) {
                    $css .= $content;
                    continue;
                }

                $path = dirname($file);
                $file = $this->getAbsoluteFilePath($file);

                $content = '';

                if (!file_exists($file)) {
                    \Yii::warning(sprintf('Asset file not found `%s`', $file), __METHOD__);
                } elseif (!is_readable($file)) {
                    \Yii::warning(sprintf('Asset file not readable `%s`', $file), __METHOD__);
                } else {
                    $content = file_get_contents($file);
                }

                $result = [];

                preg_match_all('|url\(([^)]+)\)|i', $content, $m);

                if (isset($m[0])) {
                    foreach ((array)$m[0] as $k => $v) {
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

                $this->expandImports($content);

                $this->convertMediaTypeAttributeToMediaQuery($html, $content);

                if ($this->view->minifyCss) {
                    $content = \Minify_CSSmin::minify($content);
                }

                $this->saveToCache($cacheKey, $content);

                $css .= $content;
            }

            $charsets = $this->collectCharsets($css);
            $imports = $this->collectImports($css);
            $fonts = $this->collectFonts($css);

            if (false !== $this->view->forceCharset) {
                $charsets = '@charset "' . (string)$this->view->forceCharset . '";' . "\n";
            }

            file_put_contents($resultFile, $charsets . $imports . $fonts . $css);

            if (false !== $this->view->fileMode) {
                @chmod($resultFile, $this->view->fileMode);
            }
        }

        $file = $this->prepareResultFile($resultFile);

        $this->view->cssFiles[$file] = Html::cssFile($file);
    }

    /**
     * @param string $code
     */
    protected function expandImports(&$code)
    {
        if (true !== $this->view->expandImports) {
            return;
        }

        preg_match_all('|\@import\s([^;]+);|i', str_replace('&amp;', '&', $code), $m);

        if (!isset($m[0])) {
            return;
        }

        foreach ((array)$m[0] as $k => $v) {
            $importUrl = $m[1][$k];

            if (empty($importUrl)) {
                continue;
            }

            $importContent = $this->_getImportContent($importUrl);

            if (null === $importContent) {
                continue;
            }

            $code = str_replace($m[0][$k], $importContent, $code);
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

        foreach ((array)$m[0] as $string) {
            $string = $handler($string);
            $code = str_replace($string, '', $code);

            $result .= $string . PHP_EOL;
        }

        return $result;
    }

    /**
     * @param string|null $url
     * @return null|string
     */
    protected function _getImportContent($url)
    {
        if (null === $url || '' === $url) {
            return null;
        }

        if (0 !== mb_strpos($url, 'url(')) {
            return null;
        }

        $currentUrl = str_replace(['url(\'', 'url("', 'url(', '\')', '")', ')'], '', $url);

        if (0 === mb_strpos($currentUrl, '//')) {
            $currentUrl = preg_replace('|^//|', 'http://', $currentUrl, 1);
        }

        if (null === $currentUrl || '' === $currentUrl) {
            return null;
        }

        if (!in_array(mb_substr($currentUrl, 0, 4), ['http', 'ftp:'], true)) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $currentUrl = $this->view->basePath . $currentUrl;
        }

        if (false === $currentUrl) {
            return null;
        }

        return $this->_fetchImportFileContent($currentUrl);
    }

    /**
     * @param string $url
     * @return bool|string
     */
    protected function _fetchImportFileContent($url)
    {
        $context = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ];

        return file_get_contents($url, null, stream_context_create($context));
    }

    /**
     * If the <link> tag has a media="type" attribute, wrap the content in an equivalent media query
     * @param string $html HTML of the link tag
     * @param string $content CSS content
     * @return string $content CSS content wrapped with media query, if applicable
     */
    protected function convertMediaTypeAttributeToMediaQuery($html, &$content)
    {
        if (preg_match('/\bmedia=(["\'])([^"\']+)\1/i', $html, $m) && isset($m[2]) && $m[2] !== 'all') {
            $content = '@media ' . $m[2] . ' {' . $content . '}';
        }
    }
}
