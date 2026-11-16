<?php

namespace iguazoft\postman\tests;

use Yii;
use yii\console\Application;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        new Application([
            'id' => 'postman-test',
            'basePath' => __DIR__,
            'vendorPath' => dirname(__DIR__) . '/vendor',
        ]);
    }

    protected function tearDown(): void
    {
        Yii::$app = null;
        parent::tearDown();
    }

    /**
     * Flattens folders and returns requests indexed by name.
     */
    protected function requestsByName(array $collection): array
    {
        $result = [];
        foreach ($collection['item'] as $item) {
            if (isset($item['request'])) {
                $result[$item['name']] = $item;
                continue;
            }
            foreach ($item['item'] as $request) {
                $request['_folder'] = $item['name'];
                $result[$request['name']] = $request;
            }
        }

        return $result;
    }

    protected function variables(array $collection): array
    {
        $result = [];
        foreach ($collection['variable'] as $variable) {
            $result[$variable['key']] = $variable['value'];
        }

        return $result;
    }
}
