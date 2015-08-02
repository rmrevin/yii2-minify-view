Yii 2 Minify View Component
============================
The main feature of this component - concatenate and compress files connected through "AssetBundle".

Fork of [another extension](https://github.com/rmrevin/yii2-minify-view) with some additional features:
* Ability to just concatenate files (without compression);
* Ability to choose between fast and safe methods of checking that original file was changed.

Installation
------------
The preferred way to install this extension is through [composer](https://getcomposer.org/).

Either run

`php composer.phar require --prefer-dist as-milano/yii2-minify "*"`

or add

`"as-milano/yii2-minify": "*"`

to the require section of your `composer.json` file.

Configure
---------

In the application configuration add configuration for the View component:

```php
'components' => [
    // ...
    'view' => [
        'class' => 'milano\minify\View',
        'enableMinify' => !YII_DEBUG,
        'minifyCss' => true,
        'compressCss' => true,
        'minifyCss' => true,
        'compressCss' => true,
        'minifyPath' => '@webroot/assets/minify',
        'jsPosition' => [\yii\web\View::POS_END],
        'expandImports' => true,
        'compressOutput' => true,
        'hashMethod' => 'sha'
    ]
    // ...
]
```
