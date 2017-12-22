<?php
/**
 * View.php
 * @author Revin Roman
 * @link https://rmrevin.ru
 */

namespace rmrevin\yii\minify;

use yii\base\Event;
use yii\helpers\FileHelper;
use yii\web\AssetBundle;
use yii\web\Response;

/**
 * Class View
 * @package rmrevin\yii\minify
 */
class View extends \yii\web\View
{

    /**
     * @var bool
     */
    public $enableMinify = true;

    /**
     * @var string filemtime or sha1
     */
    public $fileCheckAlgorithm = 'hash';

    /**
     * @var bool
     */
    public $concatCss = true;

    /**
     * @var bool
     */
    public $minifyCss = true;

    /**
     * @var bool
     */
    public $concatJs = true;

    /**
     * @var bool
     */
    public $minifyJs = true;

    /**
     * @var bool
     */
    public $minifyOutput = false;

    /**
     * @var string path alias to web base (in url)
     */
    public $webPath = '@web';

    /**
     * @var string path alias to web base (absolute)
     */
    public $basePath = '@webroot';

    /**
     * @var string path alias to save minify result
     */
    public $minifyPath = '@webroot/minify';

    /**
     * @var array positions of js files to be minified
     */
    public $jsPosition = [self::POS_END, self::POS_HEAD];

    /**
     * @var array options of minified js files
     */
    public $jsOptions = [];

    /**
     * @var bool|string charset forcibly assign, otherwise will use all of the files found charset
     */
    public $forceCharset = false;

    /**
     * @var bool whether to change @import on content
     */
    public $expandImports = true;

    /**
     * @var int|bool chmod of minified file. If false chmod not set
     */
    public $fileMode = 0664;

    /**
     * @var array schemes that will be ignored during normalization url
     */
    public $schemas = ['//', 'http://', 'https://', 'ftp://'];

    /**
     * @var array options for compressing output result
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     */
    public $compressOptions = [];

    /**
     * @var array
     */
    public $excludeBundles = [];

    /**
     * @var array
     */
    public $excludeFiles = [];

    /**
     * @var array
     */
    public $hashAlgos = ['md5', 'tiger160,3', 'sha1', 'tiger192,4'];

    /**
     * @var null|string
     */
    public $currentHashAlgo;

    /**
     * @var \yii\caching\CacheInterface|string|null
     */
    public $cache;

    /**
     * @throws \rmrevin\yii\minify\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\InvalidParamException
     * @throws \yii\base\Exception
     */
    public function init()
    {
        parent::init();

        $this->basePath = \Yii::getAlias($this->basePath);
        $this->webPath = \Yii::getAlias($this->webPath);
        $this->minifyPath = \Yii::getAlias($this->minifyPath);

        if (null !== $this->cache && is_string($this->cache)) {
            $this->cache = \Yii::$app->get($this->cache);
        }

        foreach ($this->excludeBundles as $bundleClass) {
            if (!class_exists($bundleClass)) {
                continue;
            }

            /** @var AssetBundle $Bundle */
            $Bundle = new $bundleClass;

            if (!empty($Bundle->css)) {
                $this->excludeFiles = array_merge($this->excludeFiles, $Bundle->css);
            }

            if (!empty($Bundle->js)) {
                $this->excludeFiles = array_merge($this->excludeFiles, $Bundle->js);
            }
        }

        $hashAlgos = hash_algos();

        foreach ($this->hashAlgos as $alog) {
            if (!in_array($alog, $hashAlgos, true)) {
                continue;
            }

            $this->currentHashAlgo = $alog;
            break;
        }

        if (null === $this->currentHashAlgo) {
            throw new Exception('Unable to determine the hash algorithm.');
        }

        $minifyPath = $this->minifyPath;

        if (!file_exists($minifyPath)) {
            FileHelper::createDirectory($minifyPath);
        }

        if (!is_readable($minifyPath)) {
            throw new Exception('Directory for compressed assets is not readable.');
        }

        if (!is_writable($minifyPath)) {
            throw new Exception('Directory for compressed assets is not writable.');
        }

        if (true === $this->enableMinify && true === $this->minifyOutput) {
            \Yii::$app->response->on(Response::EVENT_BEFORE_SEND, [$this, 'compressOutput']);
        }
    }

    /**
     * @param \yii\base\Event $event
     * @codeCoverageIgnore
     */
    public function compressOutput(Event $event)
    {
        /** @var Response $Response */
        $Response = $event->sender;

        if (Response::FORMAT_HTML !== $Response->format) {
            return;
        }

        if (!empty($Response->data)) {
            $Response->data = \Minify_HTML::minify($Response->data, $this->compressOptions);
        }

        if (!empty($Response->content)) {
            $Response->content = \Minify_HTML::minify($Response->content, $this->compressOptions);
        }
    }

    /**
     * @inheritdoc
     */
    public function endBody()
    {
        $this->trigger(self::EVENT_END_BODY);

        echo self::PH_BODY_END;

        foreach (array_keys($this->assetBundles) as $bundle) {
            $this->registerAssetFiles($bundle);
        }

        if (true === $this->enableMinify) {
            (new components\CSS($this))->export();
            (new components\JS($this))->export();
        }
    }
}
