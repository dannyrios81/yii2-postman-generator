<?php

namespace iguazoft\postman\tests;

use iguazoft\postman\AnnotationParser;
use iguazoft\postman\tests\fixtures\controllers\UserController;
use iguazoft\postman\tests\fixtures\controllers\v2\ProductController;

class AnnotationParserTest extends TestCase
{
    private function requests(string $class): array
    {
        $data = (new AnnotationParser())->parseClass($class);
        $byName = [];
        foreach ($data['requests'] as $request) {
            $byName[$request['name']] = $request;
        }

        return $byName;
    }

    public function testClassAnnotations()
    {
        $data = (new AnnotationParser())->parseClass(UserController::class);

        $this->assertSame('Shop API', $data['collection']);
        $this->assertSame('Public API (v1), used by the mobile app', $data['description']);
        $this->assertSame('https://api.shop.test', $data['baseUrl']);
        $this->assertSame(['apiVersion' => 'v1'], $data['variables']);
    }

    public function testOnlyAnnotatedActionsAreParsed()
    {
        $this->assertSame(['List users', 'Create user', 'View user'], array_keys($this->requests(UserController::class)));
    }

    public function testDescriptionKeepsParenthesesAndCommas()
    {
        $this->assertSame('Returns users (paginated), newest first', $this->requests(UserController::class)['List users']['description']);
    }

    public function testRepeatedQueryAndHeaderAnnotations()
    {
        $requests = $this->requests(UserController::class);

        $this->assertSame(['page' => '1', 'per-page' => '20'], $requests['List users']['queryParams']);
        $this->assertSame(['X-Request-Id' => '{{$guid}}'], $requests['Create user']['headers']);
    }

    public function testMethodIsUppercasedAndDefaultsToGet()
    {
        $requests = $this->requests(UserController::class);

        $this->assertSame('POST', $requests['Create user']['method']);
        $this->assertSame('GET', $requests['List users']['method']);
    }

    public function testMultilineJsonBodyWithNestedStructures()
    {
        $this->assertSame([
            'name' => 'John (Johnny) Doe',
            'roles' => ['admin', 'editor'],
            'address' => ['city' => 'Bogotá'],
        ], $this->requests(UserController::class)['Create user']['body']);
    }

    public function testAuthVariants()
    {
        $users = $this->requests(UserController::class);
        $products = $this->requests(ProductController::class);

        $this->assertSame(['type' => 'bearer', 'token' => '{{token}}'], $users['List users']['auth']);
        $this->assertSame(['type' => 'basic', 'username' => '{{username}}', 'password' => '{{password}}'], $users['View user']['auth']);
        $this->assertSame(['type' => 'apikey', 'key' => 'X-Api-Key', 'value' => '{{apiKey}}'], $products['Delete product']['auth']);
    }

    public function testSingleQuotedAndUnquotedArguments()
    {
        $request = $this->requests(ProductController::class)['Delete product'];

        $this->assertSame('Delete product', $request['name']);
        $this->assertSame('DELETE', $request['method']);
    }

    public function testHiddenFlag()
    {
        $requests = $this->requests(ProductController::class);

        $this->assertTrue($requests['Internal sync']['hidden']);
        $this->assertFalse($requests['Delete product']['hidden']);
    }
}
