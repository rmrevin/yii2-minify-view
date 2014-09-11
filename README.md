Yii 2 Minify View Component
===========================

[![Dependency Status](https://www.versioneye.com/user/projects/54119b4b9e1622a6510000e1/badge.svg?style=flat)](https://www.versioneye.com/user/projects/54119b4b9e1622a6510000e1)

Installation
------------
Add in `composer.json`:
```
{
    "require": {
        "rmrevin/yii2-minify-view": "1.3.1"
    }
}
```

Configure
-----
```php
<?
return [
	// ...
	'components' => [
		// ...
		'view' => [
			'class' => '\rmrevin\yii\minify\View',
			'base_path' => '@app/web', // path alias to web base
			'minify_path' => '@app/web/minify', // path alias to save minify result
			'js_position' => [ \yii\web\View::POS_END ], // positions of js files to be minified
			'force_charset' => 'UTF-8', // charset forcibly assign, otherwise will use all of the files found charset
			'expand_imports' => true, // whether to change @import on content
		]
	]
];
```
