<?php

namespace modules\catalog\assets;

use yii\web\AssetBundle;

/**
 * Class ProductAsset
 * @package modules\catalog\assets
 */
class ProductAsset extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath;

    /**
     * @var array
     */
    public $css = [];

    /**
     * @var array
     */
    public $js = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->sourcePath = __DIR__ . '/src';
        $this->css = [
            'css/product.css',
        ];
        $this->js = [
            'js/product.js',
        ];
    }

    /**
     * @var array
     */
    public $publishOptions = [
        'forceCopy' => true,
    ];

    /**
     * @var array
     */
    public $depends = [
        'yii\web\JqueryAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}
