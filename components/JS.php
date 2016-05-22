<?php
/**
 * JS.php
 * @author Revin Roman
 * @link https://rmrevin.com
 */

namespace rmrevin\yii\minify\components;

use yii\helpers\Html;

/**
 * Class JS
 * @package rmrevin\yii\minify\components
 */
class JS extends MinifyComponent
{

    public function minify()
    {
        $jsFiles = $this->view->jsFiles;

        foreach ($jsFiles as $position => $files) {
            if (false === in_array($position, $this->view->js_position, true)) {
                $this->view->jsFiles[$position] = [];
                foreach ($files as $file => $html) {
                    $this->view->jsFiles[$position][$file] = $html;
                }
            } else {
                $this->view->jsFiles[$position] = [];

                $toMinify = [];

                foreach ($files as $file => $html) {
                    if ($this->thisFileNeedMinify($file, $html)) {
                        $toMinify[$file] = $html;
                    } else {
                        if (!empty($toMinify)) {
                            $this->process($position, $toMinify);

                            $toMinify = [];
                        }

                        $this->view->jsFiles[$position][$file] = $html;
                    }
                }

                if (!empty($toMinify)) {
                    $this->process($position, $toMinify);
                }

                unset($toMinify);
            }
        }
    }

    /**
     * @param integer $position
     * @param array $files
     */
    protected function process($position, $files)
    {
        $resultFile = sprintf('%s/%s.js', $this->view->minify_path, $this->_getSummaryFilesHash($files));
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

            if (false !== $this->view->file_mode) {
                @chmod($resultFile, $this->view->file_mode);
            }
        }

        $file = sprintf('%s%s', \Yii::getAlias($this->view->web_path), str_replace(\Yii::getAlias($this->view->base_path), '', $resultFile));

        $this->view->jsFiles[$position][$file] = Html::jsFile($file);
    }

    /**
     * @param string $code
     */
    protected function removeJsComments(&$code)
    {
        // @todo
        if (true === $this->view->removeComments) {
            //$code = preg_replace('', '', $code);
        }
    }
}