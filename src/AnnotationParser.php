<?php

namespace iguazoft\postman;

use ReflectionClass;
use ReflectionMethod;

/**
 * Parses `@Postman\...` annotations from controller PHPDoc blocks.
 *
 * Supported argument syntax:
 *
 * ```
 * @Postman\Request("List users")                     double quotes
 * @Postman\Request('List users')                     single quotes
 * @Postman\Request(List users)                       unquoted (no commas or parentheses)
 * @Postman\Header("X-Api-Key", "{{apiKey}}")         several arguments
 * @Postman\Description("Returns users (paginated)")  parentheses and commas inside quotes are fine
 * @Postman\Body({ "name": "John" })                  JSON, may span several lines
 * @Postman\Hidden                                    flag, with or without ()
 * ```
 */
class AnnotationParser
{
    /**
     * Parses the class-level annotations and every annotated action of a controller.
     *
     * @param string $className Fully qualified class name
     * @return array
     */
    public function parseClass(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $classDoc = $this->cleanDocBlock((string) $reflection->getDocComment());

        $result = [
            'class' => $className,
            'collection' => $this->parseAnnotation($classDoc, 'Collection'),
            'description' => $this->parseAnnotation($classDoc, 'Description'),
            'baseUrl' => $this->parseAnnotation($classDoc, 'BaseUrl'),
            'variables' => $this->parseKeyValueAnnotations($classDoc, 'Variable'),
            'tests' => $this->parseTestAnnotations($classDoc, $className),
            'requests' => [],
        ];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic() || $name === 'actions' || strpos($name, 'action') !== 0 || strlen($name) <= 6) {
                continue;
            }
            $requestData = $this->parseMethod($method);
            if ($requestData !== null) {
                $result['requests'][] = $requestData;
            }
        }

        return $result;
    }

    /**
     * Parses the annotations of a single action. Returns null when the action has no `@Postman\Request`.
     *
     * @param ReflectionMethod $method
     * @return array|null
     */
    protected function parseMethod(ReflectionMethod $method): ?array
    {
        $rawDoc = (string) $method->getDocComment();
        if ($rawDoc === '') {
            return null;
        }
        $doc = $this->cleanDocBlock($rawDoc);

        $name = $this->parseAnnotation($doc, 'Request');
        if ($name === null || $name === '') {
            return null;
        }

        return [
            'name' => $name,
            'folder' => $this->parseAnnotation($doc, 'Folder'),
            'method' => strtoupper($this->parseAnnotation($doc, 'Method') ?: 'GET'),
            'path' => $this->parseAnnotation($doc, 'Path'),
            'description' => $this->parseAnnotation($doc, 'Description'),
            'auth' => $this->parseAuthAnnotation($doc),
            'body' => $this->parseBodyAnnotation($doc),
            'headers' => $this->parseKeyValueAnnotations($doc, 'Header'),
            'queryParams' => $this->parseKeyValueAnnotations($doc, 'Query'),
            'hidden' => $this->parseBooleanAnnotation($doc, 'Hidden'),
            'tests' => $this->parseTestAnnotations($doc, $method->class . '::' . $method->getName() . '()'),
        ];
    }

    /**
     * Parses repeated `@Postman\Test("type", "value"[, "expected"])` annotations.
     *
     * @param string $doc
     * @param string $location Class or method, used in error messages
     * @return array List of argument lists
     * @throws InvalidAnnotationException
     */
    protected function parseTestAnnotations(string $doc, string $location): array
    {
        $tests = [];
        foreach ($this->findAnnotationArguments($doc, 'Test') as $args) {
            $error = TestScriptBuilder::validate($args);
            if ($error !== null) {
                throw new InvalidAnnotationException("$location: $error");
            }
            $tests[] = $args;
        }

        return $tests;
    }

    /**
     * Removes the PHPDoc delimiters and the leading `*` of every line.
     *
     * @param string $doc
     * @return string
     */
    protected function cleanDocBlock(string $doc): string
    {
        $doc = preg_replace('#^\s*/\*\*#', '', $doc);
        $doc = preg_replace('#\*/\s*$#', '', $doc);

        return preg_replace('/^[ \t]*\*[ \t]?/m', '', $doc);
    }

    /**
     * Returns true when a flag annotation such as `@Postman\Hidden` is present.
     *
     * @param string $doc
     * @param string $annotation
     * @return bool
     */
    protected function parseBooleanAnnotation(string $doc, string $annotation): bool
    {
        return (bool) preg_match('/@Postman\\\\' . preg_quote($annotation, '/') . '(?![\w\\\\])/', $doc);
    }

    /**
     * Returns the first argument of the first occurrence of an annotation.
     *
     * @param string $doc
     * @param string $annotation Annotation name without the `@Postman\` prefix
     * @return string|null
     */
    protected function parseAnnotation(string $doc, string $annotation): ?string
    {
        $all = $this->findAnnotationArguments($doc, $annotation);

        return isset($all[0][0]) ? $all[0][0] : null;
    }

    /**
     * Parses repeated `@Postman\X("key", "value")` annotations into an ordered key => value map.
     *
     * @param string $doc
     * @param string $annotation
     * @return array
     */
    protected function parseKeyValueAnnotations(string $doc, string $annotation): array
    {
        $results = [];
        foreach ($this->findAnnotationArguments($doc, $annotation) as $args) {
            if (!isset($args[0]) || $args[0] === '') {
                continue;
            }
            $results[$args[0]] = isset($args[1]) ? $args[1] : '';
        }

        return $results;
    }

    /**
     * Parses `@Postman\Auth`.
     *
     * - `@Postman\Auth("bearer")` / `@Postman\Auth("bearer", "{{token}}")`
     * - `@Postman\Auth("basic", "{{username}}", "{{password}}")`
     * - `@Postman\Auth("apikey", "X-Api-Key", "{{apiKey}}")` (header)
     * - `@Postman\Auth("noauth")`
     *
     * @param string $doc
     * @return array|null
     */
    protected function parseAuthAnnotation(string $doc): ?array
    {
        $all = $this->findAnnotationArguments($doc, 'Auth');
        if (!isset($all[0][0]) || $all[0][0] === '') {
            return null;
        }

        $args = $all[0];
        $type = strtolower($args[0]);

        switch ($type) {
            case 'basic':
                return [
                    'type' => 'basic',
                    'username' => isset($args[1]) ? $args[1] : '{{username}}',
                    'password' => isset($args[2]) ? $args[2] : '{{password}}',
                ];
            case 'apikey':
                return [
                    'type' => 'apikey',
                    'key' => isset($args[1]) ? $args[1] : 'X-Api-Key',
                    'value' => isset($args[2]) ? $args[2] : '{{apiKey}}',
                ];
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'token' => isset($args[1]) ? $args[1] : '{{token}}',
                ];
            default:
                return ['type' => $type];
        }
    }

    /**
     * Parses `@Postman\Body({...})` or `@Postman\Body([...])`. Invalid JSON is ignored.
     *
     * @param string $doc
     * @return array|null
     */
    protected function parseBodyAnnotation(string $doc): ?array
    {
        if (!preg_match('/@Postman\\\\Body\s*\(\s*/', $doc, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        $open = isset($doc[$start]) ? $doc[$start] : '';
        if ($open !== '{' && $open !== '[') {
            return null;
        }
        $close = $open === '{' ? '}' : ']';

        $depth = 0;
        $inString = false;
        $length = strlen($doc);
        for ($i = $start; $i < $length; $i++) {
            $char = $doc[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($doc, $start, $i - $start + 1), true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * Finds every occurrence of `@Postman\<annotation>(...)` and returns the argument list of each one.
     *
     * @param string $doc Cleaned doc block
     * @param string $annotation
     * @return array<int, array<int, string>>
     */
    protected function findAnnotationArguments(string $doc, string $annotation): array
    {
        $pattern = '/@Postman\\\\' . preg_quote($annotation, '/') . '\s*\(/';
        if (!preg_match_all($pattern, $doc, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $results = [];
        foreach ($matches[0] as $match) {
            $args = $this->readArguments($doc, $match[1] + strlen($match[0]));
            if ($args !== null) {
                $results[] = $args;
            }
        }

        return $results;
    }

    /**
     * Reads a comma separated argument list starting right after the opening parenthesis.
     * Quoted arguments may contain commas, parentheses and escaped quotes.
     *
     * @param string $doc
     * @param int $offset
     * @return array|null Null when the closing parenthesis is missing
     */
    protected function readArguments(string $doc, int $offset): ?array
    {
        $args = [];
        $length = strlen($doc);
        $i = $offset;

        while ($i < $length) {
            while ($i < $length && ctype_space($doc[$i])) {
                $i++;
            }
            if ($i >= $length) {
                return null;
            }

            $char = $doc[$i];
            if ($char === ')') {
                return $args;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $value = '';
                $i++;
                while ($i < $length && $doc[$i] !== $quote) {
                    if ($doc[$i] === '\\' && $i + 1 < $length && ($doc[$i + 1] === $quote || $doc[$i + 1] === '\\')) {
                        $i++;
                    }
                    $value .= $doc[$i];
                    $i++;
                }
                $i++; // closing quote
                $args[] = $value;
            } else {
                $value = '';
                while ($i < $length && $doc[$i] !== ',' && $doc[$i] !== ')' && $doc[$i] !== "\n") {
                    $value .= $doc[$i];
                    $i++;
                }
                $args[] = trim($value);
            }

            while ($i < $length && ctype_space($doc[$i])) {
                $i++;
            }
            if ($i < $length && $doc[$i] === ',') {
                $i++;
                continue;
            }
            if ($i < $length && $doc[$i] === ')') {
                return $args;
            }

            return null;
        }

        return null;
    }
}
