<?php

namespace iguazoft\postman;

/**
 * Turns `@Postman\Test` annotations into a Postman test script (the `pm.test(...)` lines that
 * Postman and Newman run after each request).
 *
 * | Annotation                                         | Generated assertion                              |
 * |----------------------------------------------------|--------------------------------------------------|
 * | `@Postman\Test("status", "201")`                   | status code is 201                               |
 * | `@Postman\Test("json", "data.items")`              | JSON body has the (nested) property              |
 * | `@Postman\Test("json", "data.active", "true")`     | ... and it equals `true` (JSON value or string)  |
 * | `@Postman\Test("header", "Content-Type")`          | the header is present                            |
 * | `@Postman\Test("header", "X-Version", "2")`        | ... and has this value                           |
 * | `@Postman\Test("time", "500")`                     | response time below 500 ms                       |
 * | `@Postman\Test("contains", "created")`             | body text contains the string                    |
 */
class TestScriptBuilder
{
    /**
     * Supported types and how many arguments each takes after the type: [min, max].
     */
    const TYPES = [
        'status' => [1, 1],
        'json' => [1, 2],
        'header' => [1, 2],
        'time' => [1, 1],
        'contains' => [1, 1],
    ];

    /**
     * Validates the arguments of one `@Postman\Test`. Returns an error message, or null when valid.
     *
     * @param array $args Arguments as parsed, type first
     * @return string|null
     */
    public static function validate(array $args): ?string
    {
        $type = isset($args[0]) ? strtolower(trim($args[0])) : '';
        if (!isset(self::TYPES[$type])) {
            return sprintf('unknown @Postman\Test type "%s"; use one of: %s', $type, implode(', ', array_keys(self::TYPES)));
        }

        $values = array_slice($args, 1);
        list($min, $max) = self::TYPES[$type];
        if (count($values) < $min || count($values) > $max) {
            return sprintf('@Postman\Test("%s") takes %s argument(s) after the type, %d given', $type, $min === $max ? $min : "$min to $max", count($values));
        }
        if ($values[0] === '') {
            return sprintf('@Postman\Test("%s") needs a value', $type);
        }
        if (($type === 'status' || $type === 'time') && !ctype_digit($values[0])) {
            return sprintf('@Postman\Test("%s") needs a whole number, "%s" given', $type, $values[0]);
        }

        return null;
    }

    /**
     * @param array $tests List of argument lists, each one validated with {@see validate()}
     * @return string[] Script lines
     */
    public static function build(array $tests): array
    {
        $lines = [];
        foreach ($tests as $args) {
            $type = strtolower(trim($args[0]));
            $value = $args[1];
            $expected = array_key_exists(2, $args) ? $args[2] : null;

            switch ($type) {
                case 'status':
                    $lines[] = self::test("Status code is $value", 'pm.response.to.have.status(' . (int) $value . ');');
                    break;
                case 'json':
                    $assertion = $expected === null
                        ? 'pm.expect(pm.response.json()).to.have.nested.property(' . self::js($value) . ');'
                        : 'pm.expect(pm.response.json()).to.have.nested.property(' . self::js($value) . ', ' . self::jsValue($expected) . ');';
                    $name = $expected === null ? "Response has $value" : "$value is $expected";
                    $lines[] = self::test($name, $assertion);
                    break;
                case 'header':
                    $assertion = $expected === null
                        ? 'pm.response.to.have.header(' . self::js($value) . ');'
                        : 'pm.response.to.have.header(' . self::js($value) . ', ' . self::js($expected) . ');';
                    $lines[] = self::test($expected === null ? "Header $value is present" : "Header $value is $expected", $assertion);
                    break;
                case 'time':
                    $lines[] = self::test("Response time is below {$value} ms", 'pm.expect(pm.response.responseTime).to.be.below(' . (int) $value . ');');
                    break;
                case 'contains':
                    $lines[] = self::test("Body contains $value", 'pm.expect(pm.response.text()).to.include(' . self::js($value) . ');');
                    break;
            }
        }

        return $lines;
    }

    /**
     * @param string $name
     * @param string $assertion
     * @return string
     */
    private static function test(string $name, string $assertion): string
    {
        return 'pm.test(' . self::js($name) . ', function () { ' . $assertion . ' });';
    }

    /**
     * Encodes a PHP string as a JavaScript string literal.
     *
     * @param string $value
     * @return string
     */
    private static function js(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Encodes an expected value: valid JSON (`true`, `42`, `null`, `"x"`, `[1,2]`) is used as is,
     * anything else becomes a string.
     *
     * @param string $value
     * @return string
     */
    private static function jsValue(string $value): string
    {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return self::js($value);
    }
}
