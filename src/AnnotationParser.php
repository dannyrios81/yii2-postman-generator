<?php

namespace iguazoft\postman;

use ReflectionClass;
use ReflectionMethod;

/**
 * AnnotationParser - Parsea anotaciones @Postman de PHPDoc
 */
class AnnotationParser
{
    /**
     * Parsea las anotaciones de una clase
     * 
     * @param string $className Nombre completo de la clase
     * @return array Datos parseados
     */
    public function parseClass(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $classDoc = $reflection->getDocComment();

        $result = [
            'class' => $className,
            'collection' => $this->parseAnnotation($classDoc, 'Collection'),
            'description' => $this->parseAnnotation($classDoc, 'Description'),
            'baseUrl' => $this->parseAnnotation($classDoc, 'BaseUrl'),
            'variables' => $this->parseMultipleAnnotations($classDoc, 'Variable'),
            'requests' => [],
        ];

        // Parsear métodos (actions)
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'action') === 0 && $method->getName() !== 'actions') {
                $requestData = $this->parseMethod($method);
                if ($requestData) {
                    $result['requests'][] = $requestData;
                }
            }
        }

        return $result;
    }

    /**
     * Parsea las anotaciones de un método
     * 
     * @param ReflectionMethod $method
     * @return array|null
     */
    protected function parseMethod(ReflectionMethod $method): ?array
    {
        $doc = $method->getDocComment();
        if (!$doc) {
            return null;
        }

        $name = $this->parseAnnotation($doc, 'Request');
        if (!$name) {
            // Si no tiene @Postman\Request, no incluir
            return null;
        }

        return [
            'name' => $name,
            'folder' => $this->parseAnnotation($doc, 'Folder'),
            'method' => $this->parseAnnotation($doc, 'Method') ?: 'GET',
            'path' => $this->parseAnnotation($doc, 'Path'),
            'description' => $this->parseAnnotation($doc, 'Description'),
            'auth' => $this->parseAuthAnnotation($doc),
            'body' => $this->parseBodyAnnotation($doc),
            'headers' => $this->parseMultipleAnnotations($doc, 'Header'),
            'queryParams' => $this->parseMultipleAnnotations($doc, 'Query'),
            'hidden' => $this->parseBooleanAnnotation($doc, 'Hidden'),
        ];
    }

    /**
     * Detecta si existe una anotación booleana (sin parámetros)
     * 
     * @param string $doc
     * @param string $annotation
     * @return bool
     */
    protected function parseBooleanAnnotation(string $doc, string $annotation): bool
    {
        // Soporta: @Postman\Hidden o @Postman\Hidden()
        $pattern = '/@Postman\\\\' . $annotation . '(?:\s*\(\s*\))?(?:\s|$|\*)/';
        return (bool) preg_match($pattern, $doc);
    }

    /**
     * Extrae el valor de una anotación simple
     * 
     * @param string $doc PHPDoc string
     * @param string $annotation Nombre de la anotación (sin @Postman\)
     * @return string|null
     */
    protected function parseAnnotation(string $doc, string $annotation): ?string
    {
        // Soporta: @Postman\Annotation("valor") o @Postman\Annotation(valor)
        $pattern = '/@Postman\\\\' . $annotation . '\s*\(\s*["\']?([^"\')\n]+)["\']?\s*\)/';

        if (preg_match($pattern, $doc, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extrae múltiples anotaciones del mismo tipo
     * 
     * @param string $doc
     * @param string $annotation
     * @return array
     */
    protected function parseMultipleAnnotations(string $doc, string $annotation): array
    {
        $pattern = '/@Postman\\\\' . $annotation . '\s*\(\s*["\']?([^"\')\n]+)["\']?\s*(?:,\s*["\']?([^"\')\n]+)["\']?)?\s*\)/';
        $results = [];

        if (preg_match_all($pattern, $doc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($match[2])) {
                    $results[$match[1]] = $match[2];
                } else {
                    $results[] = $match[1];
                }
            }
        }

        return $results;
    }

    /**
     * Parsea la anotación @Postman\Auth
     * 
     * @param string $doc
     * @return array|null
     */
    protected function parseAuthAnnotation(string $doc): ?array
    {
        $pattern = '/@Postman\\\\Auth\s*\(\s*["\']?(\w+)["\']?\s*(?:,\s*["\']?([^"\')\n]+)["\']?)?\s*\)/';

        if (preg_match($pattern, $doc, $matches)) {
            return [
                'type' => $matches[1],
                'token' => $matches[2] ?? '{{token}}',
            ];
        }

        return null;
    }

    /**
     * Parsea la anotación @Postman\Body (JSON)
     * 
     * @param string $doc
     * @return array|null
     */
    protected function parseBodyAnnotation(string $doc): ?array
    {
        // Buscar @Postman\Body({ ... })
        $pattern = '/@Postman\\\\Body\s*\(\s*(\{[\s\S]*?\})\s*\)/';

        if (preg_match($pattern, $doc, $matches)) {
            $jsonStr = $matches[1];
            // Limpiar asteriscos de PHPDoc
            $jsonStr = preg_replace('/^\s*\*\s*/m', '', $jsonStr);
            $decoded = json_decode($jsonStr, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}
