<?php
/**
 * View.php
 * @author Roman Revim
 * @link http://phptime.ru
 */

namespace rmrevin\yii\minify;

use yii\helpers\FileHelper;
use yii\helpers\Html;

class View extends \yii\web\View
{

	public $base_path = '@app/web';

	public $minify_path = '@app/web/minify';

	public $file_mode = 0664;

	public $schemas = [
		'//', 'http://', 'https://', 'ftp://'
	];

	public function init()
	{
		parent::init();

		$minify_path = $this->minify_path = \Yii::getAlias($this->minify_path);
		if (!file_exists($minify_path)) {
			FileHelper::createDirectory($minify_path);
		}
		if (!is_readable($minify_path)) {
			throw new \RuntimeException(\Yii::t('app', 'Directory for compressed assets is not readable.'));
		}
		if (!is_writable($minify_path)) {
			throw new \RuntimeException(\Yii::t('app', 'Directory for compressed assets is not writable.'));
		}
	}

	public function endPage($ajaxMode = false)
	{
		$this->trigger(self::EVENT_END_PAGE);

		$content = ob_get_clean();
		foreach (array_keys($this->assetBundles) as $bundle) {
			$this->registerAssetFiles($bundle);
		}

		$this->minify();

		echo strtr($content, [
			self::PH_HEAD => $this->renderHeadHtml(),
			self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
			self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
		]);

		$this->clear();
	}

	private function registerAssetFiles($name)
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
		$long_hash = '';
		if (!empty($this->cssFiles)) {
			$css_files = array_keys($this->cssFiles);
			foreach ($css_files as $file) {
				$file = \Yii::getAlias($this->base_path) . $file;
				$hash = sha1_file($file);
				$long_hash .= $hash;
			}
			$long_hash = sha1($long_hash);

			$css_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . $long_hash . '.css';
			if (!file_exists($css_minify_file)) {
				$data = '';
				$CssMin = new \CSSmin();
				foreach ($css_files as $file) {
					$content = file_get_contents(\Yii::getAlias($this->base_path) . $file);
					preg_match_all('|url\(([^)]+)\)|is', $content, $m);
					if (!empty($m[0])) {
						$path = dirname($file);
						$result = [];
						foreach ($m[0] as $k => $v) {
							$url = str_replace(['\'', '"'], '', $m[1][$k]);
							if (preg_match('#^(' . implode('|', $this->schemas) . ')#is', $url)) {
								$result[$m[1][$k]] = '\'' . $url . '\'';
							} else {
								$result[$m[1][$k]] = '\'' . $path . DIRECTORY_SEPARATOR . $url . '\'';
							}
						}
						$content = str_replace(array_keys($result), array_values($result), $content);
					}

					$data .= $CssMin->run($content) . ';' . PHP_EOL;
				}
				file_put_contents($css_minify_file, $data);
				chmod($css_minify_file, $this->file_mode);
			}

			$css_file = str_replace(\Yii::getAlias($this->base_path), '', $css_minify_file);
			$this->cssFiles = [$css_file => Html::cssFile($css_file)];
		}

		if (!empty($this->jsFiles)) {
			$only_pos = [self::POS_END];
			$js_files = $this->jsFiles;
			foreach ($js_files as $position => $files) {
				if (false === in_array($position, $only_pos)) {
					$this->jsFiles[$position] = [];
					foreach ($files as $file => $html) {
						$this->jsFiles[$position][$file] = Html::jsFile($file);
					}
				} else {
					$this->jsFiles[$position] = [];

					$long_hash = '';
					foreach ($files as $file => $html) {
						$file = \Yii::getAlias($this->base_path) . $file;
						$hash = sha1_file($file);
						$long_hash .= $hash;
					}
					$long_hash = sha1($long_hash);

					$js_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . $long_hash . '.js';
					if (!file_exists($js_minify_file)) {
						$data = '';
						foreach ($files as $file => $html) {
							$file = \Yii::getAlias($this->base_path) . $file;
							$data .= \JSMin::minify(file_get_contents($file)) . ';' . PHP_EOL;
						}
						file_put_contents($js_minify_file, $data);
						chmod($js_minify_file, $this->file_mode);
					}

					$js_file = str_replace(\Yii::getAlias($this->base_path), '', $js_minify_file);
					$this->jsFiles[$position][$js_file] = Html::jsFile($js_file);
				}
			}
		}
	}
} 