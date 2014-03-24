Yii 2 Minify View Component
==============================

Installation
------------
Add in `composer.json`:
```
{
    "require": {
        "rmrevin/yii2-minify-view": "*"
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
			'base_path' => '@app/web', // path to web base,
			'minify_path' => '@app/web/minify', // path to save minify result
		]
	]
];
```