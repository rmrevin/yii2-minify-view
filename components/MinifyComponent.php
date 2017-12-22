<?php
/**
 * Minify.php
 * @author Revin Roman
 * @link https://rmrevin.com
 */

namespace rmrevin\yii\minify\components;

use rmrevin\yii\minify\View;
use yii\caching\Cache;
use yii\caching\TagDependency;

/**
 * Class MinifyComponent
 * @package rmrevin\yii\minify\components
 */
abstract class MinifyComponent
{

    const CACHE_TAG = 'minify-view-tag';

    /**
     * @var View
     */
    protected $view;

    /**
     * MinifyComponent constructor.
     * @param View $view
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }

    abstract public function export();

    /**
     * @param string $file
     * @return string
     */
    protected function getAbsoluteFilePath($file)
    {
        $basePath = $this->view->basePath;
        $webPath = $this->view->webPath;

        return $basePath . str_replace($webPath, '', $this->cleanFileName($file));
    }

    /**
     * @param string $file
     * @return string
     */
    protected function cleanFileName($file)
    {
        return false !== mb_strpos($file, '?')
            ? parse_url($file, PHP_URL_PATH)
            : $file;
    }

    /**
     * @param string $file
     * @param string $html
     * @return bool
     */
    protected function thisFileNeedMinify($file, $html)
    {
        return !$this->isUrl($file, false)
            && !$this->isContainsConditionalComment($html)
            && !$this->isExcludedFile($file);
    }

    /**
     * @param string $url
     * @param boolean $checkSlash
     * @return bool
     */
    protected function isUrl($url, $checkSlash = true)
    {
        $schemas = array_map(function ($val) {
            return str_replace('/', '\/', $val);
        }, $this->view->schemas);

        $regexp = '#^(' . implode('|', $schemas) . ')#i';

        if ($checkSlash) {
            $regexp = '#^(/|\\\\|' . implode('|', $schemas) . ')#i';
        }

        return (bool)preg_match($regexp, $url);
    }

    /**
     * @param string $string
     * @return bool
     */
    protected function isContainsConditionalComment($string)
    {
        return mb_strpos($string, '<![endif]-->') !== false;
    }

    /**
     * @param string $file
     * @return bool
     */
    protected function isExcludedFile($file)
    {
        $result = false;

        foreach ((array)$this->view->excludeFiles as $excludedFile) {
            if (!preg_match('!' . $excludedFile . '!i', $file)) {
                continue;
            }

            $result = true;
            break;
        }

        return $result;
    }

    /**
     * @param string $resultFile
     * @return string
     */
    protected function prepareResultFile($resultFile)
    {
        $basePath = $this->view->basePath;
        $webPath = $this->view->webPath;

        $file = sprintf('%s%s', $webPath, str_replace($basePath, '', $resultFile));

        $AssetManager = $this->view->getAssetManager();

        if ($AssetManager->appendTimestamp && ($timestamp = @filemtime($resultFile)) > 0) {
            $file .= '?v=' . $timestamp;
        }

        return $file;
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

            if (!$this->thisFileNeedMinify($file, $html) || !file_exists($path)) {
                continue;
            }

            switch ($this->view->fileCheckAlgorithm) {
                default:
                case 'filemtime':
                    $result .= filemtime($path) . $file;
                    break;
                case 'sha1': // deprecated
                case 'hash':
                    $result .= hash_file($this->view->currentHashAlgo, $path);
                    break;
            }
        }

        return hash($this->view->currentHashAlgo, $result);
    }

    /**
     * @param string $file
     * @return string
     */
    protected function buildCacheKey($file)
    {
        return hash('sha1', __CLASS__ . '/' . $file);
    }

    /**
     * @param string $key
     * @return string|false
     */
    protected function getFromCache($key)
    {
        if ($this->view->cache instanceof Cache) {
            return $this->view->cache->get($key);
        }

        return false;
    }

    /**
     * @param string $key
     * @param string $content
     * @return bool
     */
    protected function saveToCache($key, $content)
    {
        if ($this->view->cache instanceof Cache) {
            return $this->view->cache->set($key, $content, null, new TagDependency(['tags' => static::CACHE_TAG]));
        }

        return false;
    }
}
