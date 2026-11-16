<?php

namespace iguazoft\postman\tests;

use iguazoft\postman\CollectionBuilder;
use iguazoft\postman\PostmanGenerator;

class PostmanGeneratorTest extends TestCase
{
    private function generate(array $config = []): array
    {
        $generator = new PostmanGenerator(array_merge([
            'scanDirs' => ['@tests/fixtures/controllers'],
        ], $config));

        return $generator->generateArray();
    }

    public function testRequestsFromAllControllersAreKept()
    {
        // Regression: a second controller with @Postman\Collection used to wipe the requests of the first one.
        $this->assertSame(
            ['Create user', 'Delete product', 'List users', 'View user'],
            $this->sortedNames($this->generate())
        );
    }

    public function testCommentContainingTheWordClassDoesNotBreakDiscovery()
    {
        $this->assertArrayHasKey('List users', $this->requestsByName($this->generate()));
    }

    public function testFirstCollectionAnnotationWinsUnlessConfigured()
    {
        $this->assertSame('Shop API', $this->generate()['info']['name']);
        $this->assertSame('Custom', $this->generate(['collectionName' => 'Custom'])['info']['name']);
        $this->assertSame('Public API (v1), used by the mobile app', $this->generate()['info']['description']);
    }

    public function testBaseUrlPrecedence()
    {
        $this->assertSame('https://api.shop.test', $this->variables($this->generate())['baseUrl']);
        $this->assertSame('https://prod.test', $this->variables($this->generate(['variables' => ['baseUrl' => 'https://prod.test']]))['baseUrl']);
    }

    public function testVariablesAreMergedFirstDefinitionWins()
    {
        $variables = $this->variables($this->generate());

        $this->assertSame('v1', $variables['apiVersion']);
        $this->assertSame('42', $variables['storeId']);
        $this->assertSame('', $variables['token']);
    }

    public function testHiddenRequestsAreExcludedByDefault()
    {
        $this->assertArrayNotHasKey('Internal sync', $this->requestsByName($this->generate()));
        $this->assertArrayHasKey('Internal sync', $this->requestsByName($this->generate(['includeHidden' => true])));
    }

    public function testQueryParametersAreEmitted()
    {
        $url = $this->requestsByName($this->generate())['List users']['request']['url'];

        $this->assertSame('{{baseUrl}}/api/users?page=1&per-page=20', $url['raw']);
        $this->assertSame([['key' => 'page', 'value' => '1'], ['key' => 'per-page', 'value' => '20']], $url['query']);
    }

    public function testPathVariables()
    {
        $requests = $this->requestsByName($this->generate());

        $this->assertSame(['api', 'users', ':id'], $requests['View user']['request']['url']['path']);
        $this->assertSame([['key' => 'id', 'value' => '']], $requests['View user']['request']['url']['variable']);
        $this->assertSame('{{baseUrl}}/api/products/:id', $requests['Delete product']['request']['url']['raw']);
    }

    public function testFoldersAndBody()
    {
        $request = $this->requestsByName($this->generate())['Create user'];

        $this->assertSame('Users', $request['_folder']);
        $this->assertSame('raw', $request['request']['body']['mode']);
        $this->assertSame('Bogotá', json_decode($request['request']['body']['raw'], true)['address']['city']);
        $this->assertContains(['key' => 'Content-Type', 'value' => 'application/json'], $request['request']['header']);
        $this->assertContains(['key' => 'X-Request-Id', 'value' => '{{$guid}}'], $request['request']['header']);
    }

    public function testAuthObjects()
    {
        $requests = $this->requestsByName($this->generate());

        $this->assertSame('bearer', $requests['List users']['request']['auth']['type']);
        $this->assertSame('basic', $requests['View user']['request']['auth']['type']);
        $this->assertSame('{{username}}', $requests['View user']['request']['auth']['basic'][0]['value']);
        $this->assertSame('apikey', $requests['Delete product']['request']['auth']['type']);
    }

    public function testSchemaAndValidJson()
    {
        $generator = new PostmanGenerator(['scanDirs' => ['@tests/fixtures/controllers']]);
        $decoded = json_decode($generator->generateJson(), true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame(CollectionBuilder::SCHEMA, $decoded['info']['schema']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $decoded['info']['_postman_id']);
    }

    public function testCollectionIdIsStableAcrossRuns()
    {
        $this->assertSame($this->generate()['info']['_postman_id'], $this->generate()['info']['_postman_id']);
        $this->assertNotSame($this->generate()['info']['_postman_id'], $this->generate(['collectionName' => 'Other'])['info']['_postman_id']);
    }

    public function testGenerateWritesFile()
    {
        $file = sys_get_temp_dir() . '/postman-test-' . uniqid() . '/api.json';
        $generator = new PostmanGenerator(['scanDirs' => ['@tests/fixtures/controllers']]);

        $this->assertTrue($generator->generate($file));
        $this->assertFileExists($file);
        $this->assertSame('Shop API', json_decode(file_get_contents($file), true)['info']['name']);

        unlink($file);
        rmdir(dirname($file));
    }

    public function testMissingDirectoryIsIgnored()
    {
        $collection = $this->generate(['scanDirs' => ['/does/not/exist']]);

        $this->assertSame([], $collection['item']);
        $this->assertSame('API Collection', $collection['info']['name']);
    }

    public function testBuildIsIdempotent()
    {
        $builder = (new CollectionBuilder())->init('Test');
        $builder->addRequest(['name' => 'A', 'folder' => 'F', 'path' => '/a']);

        $this->assertSame($builder->build(), $builder->build());
        $this->assertCount(1, $builder->build()['item']);
    }

    private function sortedNames(array $collection): array
    {
        $names = array_keys($this->requestsByName($collection));
        sort($names);

        return $names;
    }
}
