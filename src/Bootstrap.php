<?php

namespace iguazoft\postman;

use yii\base\BootstrapInterface;
use Yii;

/**
 * Bootstrap class for yii2-postman-generator extension
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        // Add console command if running in console
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap['postman'] = [
                'class' => 'iguazoft\postman\commands\PostmanController',
            ];
        }
    }
}
