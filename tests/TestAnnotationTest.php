<?php

namespace iguazoft\postman\tests;

use iguazoft\postman\AnnotationParser;
use iguazoft\postman\InvalidAnnotationException;
use iguazoft\postman\PostmanGenerator;
use iguazoft\postman\TestScriptBuilder;
use iguazoft\postman\tests\fixtures\controllers\UserController;
use iguazoft\postman\tests\fixtures\invalid\BrokenController;

/**
 * `@Postman\Test` annotations (issue #1).
 */
class TestAnnotationTest extends TestCase
{
    private function generate(array $config = []): array
    {
        return (new PostmanGenerator(array_merge(['scanDirs' => ['@tests/fixtures/controllers']], $config)))->generateArray();
    }

    private function script(string $requestName): array
    {
        $request = $this->requestsByName($this->generate())[$requestName];
        $this->assertSame('test', $request['event'][0]['listen']);
        $this->assertSame('text/javascript', $request['event'][0]['script']['type']);

        return $request['event'][0]['script']['exec'];
    }

    public function testParserReadsClassAndMethodTests()
    {
        $data = (new AnnotationParser())->parseClass(UserController::class);

        $this->assertSame([['status', '200'], ['header', 'Content-Type', 'application/json; charset=UTF-8']], $data['tests']);
        $this->assertSame([['contains', 'username']], $data['requests'][2]['tests']);
        $this->assertSame([], $data['requests'][0]['tests']);
    }

    public function testClassTestsApplyToEveryAction()
    {
        $this->assertSame([
            'pm.test("Status code is 200", function () { pm.response.to.have.status(200); });',
            'pm.test("Header Content-Type is application/json; charset=UTF-8", function () { pm.response.to.have.header("Content-Type", "application/json; charset=UTF-8"); });',
        ], $this->script('List users'));
    }

    public function testActionTestsAreAddedAfterClassTests()
    {
        $script = $this->script('View user');

        $this->assertCount(3, $script);
        $this->assertSame('pm.test("Body contains username", function () { pm.expect(pm.response.text()).to.include("username"); });', $script[2]);
    }

    public function testActionStatusReplacesClassStatus()
    {
        $script = $this->script('Create user');

        $this->assertNotContains('pm.test("Status code is 200", function () { pm.response.to.have.status(200); });', $script);
        $this->assertContains('pm.test("Status code is 201", function () { pm.response.to.have.status(201); });', $script);
        $this->assertCount(1, preg_grep('/to\.have\.status/', $script));
    }

    public function testJsonHeaderAndTimeAssertions()
    {
        $script = $this->script('Create user');

        $this->assertContains('pm.test("Response has data.id", function () { pm.expect(pm.response.json()).to.have.nested.property("data.id"); });', $script);
        $this->assertContains('pm.test("data.active is true", function () { pm.expect(pm.response.json()).to.have.nested.property("data.active", true); });', $script);
        $this->assertContains('pm.test("Response time is below 500 ms", function () { pm.expect(pm.response.responseTime).to.be.below(500); });', $script);
    }

    public function testStringsAreEscapedForJavaScript()
    {
        $this->assertContains(
            'pm.test("data.name is John \"Johnny\" Doe", function () { pm.expect(pm.response.json()).to.have.nested.property("data.name", "John \"Johnny\" Doe"); });',
            $this->script('Create user')
        );
    }

    public function testExpectedValuesKeepTheirJsonType()
    {
        $lines = TestScriptBuilder::build([
            ['json', 'a', '42'],
            ['json', 'b', 'null'],
            ['json', 'c', '"42"'],
            ['json', 'd', '[1,2]'],
            ['json', 'e', 'plain text'],
        ]);

        $this->assertStringContainsString('property("a", 42)', $lines[0]);
        $this->assertStringContainsString('property("b", null)', $lines[1]);
        $this->assertStringContainsString('property("c", "42")', $lines[2]);
        $this->assertStringContainsString('property("d", [1,2])', $lines[3]);
        $this->assertStringContainsString('property("e", "plain text")', $lines[4]);
    }

    public function testRequestsWithoutTestsHaveNoEvent()
    {
        $requests = $this->requestsByName($this->generate());

        $this->assertArrayNotHasKey('event', $requests['Delete product']);
    }

    /**
     * @dataProvider invalidTests
     */
    public function testValidation(array $args, string $message)
    {
        $this->assertStringContainsString($message, (string) TestScriptBuilder::validate($args));
    }

    public function invalidTests(): array
    {
        return [
            'unknown type' => [['statuss', '200'], 'unknown @Postman\Test type "statuss"'],
            'missing value' => [['json'], 'takes 1 to 2 argument(s)'],
            'too many' => [['status', '200', '201'], 'takes 1 argument(s)'],
            'empty value' => [['contains', ''], 'needs a value'],
            'status not a number' => [['status', 'OK'], 'needs a whole number'],
            'time not a number' => [['time', '1.5s'], 'needs a whole number'],
        ];
    }

    public function testValidTestsPass()
    {
        $this->assertNull(TestScriptBuilder::validate(['STATUS', '204']));
        $this->assertNull(TestScriptBuilder::validate(['header', 'X-Id', 'abc']));
    }

    public function testInvalidAnnotationNamesTheAction()
    {
        $this->expectException(InvalidAnnotationException::class);
        $this->expectExceptionMessage(BrokenController::class . '::actionIndex(): unknown @Postman\Test type "statuss"');

        (new AnnotationParser())->parseClass(BrokenController::class);
    }

    public function testGeneratorDoesNotSwallowInvalidAnnotations()
    {
        $this->expectException(InvalidAnnotationException::class);

        $this->generate(['scanDirs' => ['@tests/fixtures/invalid']]);
    }
}
