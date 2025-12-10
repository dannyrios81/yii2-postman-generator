<?php

namespace iguazoft\postman;

use Yii;

/**
 * CollectionBuilder - Construye la estructura JSON de una colección Postman
 */
class CollectionBuilder
{
    protected $collection = [];
    protected $folders = [];

    /**
     * Inicializa una nueva colección
     * 
     * @param string $name Nombre de la colección
     * @param string $description Descripción
     * @param array $variables Variables de la colección
     * @return $this
     */
    public function init(string $name, string $description = '', array $variables = []): self
    {
        $this->collection = [
            'info' => [
                '_postman_id' => $this->generateUuid(),
                'name' => $name,
                'description' => $description,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [],
            'item' => [],
        ];

        // Añadir variables
        foreach ($variables as $key => $value) {
            $this->collection['variable'][] = [
                'key' => $key,
                'value' => $value,
                'type' => 'string',
            ];
        }

        // Variables por defecto
        if (!isset($variables['baseUrl'])) {
            $this->collection['variable'][] = [
                'key' => 'baseUrl',
                'value' => 'http://localhost',
                'type' => 'string',
            ];
        }
        if (!isset($variables['token'])) {
            $this->collection['variable'][] = [
                'key' => 'token',
                'value' => '',
                'type' => 'string',
            ];
        }

        return $this;
    }

    /**
     * Añade una carpeta (folder) a la colección
     * 
     * @param string $name Nombre de la carpeta
     * @param string $description Descripción
     * @return $this
     */
    public function addFolder(string $name, string $description = ''): self
    {
        if (!isset($this->folders[$name])) {
            $this->folders[$name] = [
                'name' => $name,
                'description' => $description,
                'item' => [],
            ];
        }

        return $this;
    }

    /**
     * Añade una request a la colección
     * 
     * @param array $requestData Datos del request parseados
     * @return $this
     */
    public function addRequest(array $requestData): self
    {
        $request = $this->buildRequest($requestData);

        if (!empty($requestData['folder'])) {
            $this->addFolder($requestData['folder']);
            $this->folders[$requestData['folder']]['item'][] = $request;
        } else {
            $this->collection['item'][] = $request;
        }

        return $this;
    }

    /**
     * Construye un objeto request de Postman
     * 
     * @param array $data
     * @return array
     */
    protected function buildRequest(array $data): array
    {
        $request = [
            'name' => $data['name'],
            'request' => [
                'method' => strtoupper($data['method'] ?? 'GET'),
                'header' => [],
                'url' => $this->buildUrl($data['path'] ?? ''),
            ],
            'response' => [],
        ];

        // Añadir descripción
        if (!empty($data['description'])) {
            $request['request']['description'] = $data['description'];
        }

        // Añadir autenticación
        if (!empty($data['auth'])) {
            $request['request']['auth'] = $this->buildAuth($data['auth']);
        }

        // Añadir body
        if (!empty($data['body'])) {
            $request['request']['header'][] = [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ];
            $request['request']['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($data['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }

        // Añadir headers
        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $key => $value) {
                $request['request']['header'][] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        return $request;
    }

    /**
     * Construye la URL con variables
     * 
     * @param string $path
     * @return array
     */
    protected function buildUrl(string $path): array
    {
        $pathParts = array_filter(explode('/', ltrim($path, '/')));

        return [
            'raw' => '{{baseUrl}}' . $path,
            'host' => ['{{baseUrl}}'],
            'path' => array_values($pathParts),
        ];
    }

    /**
     * Construye el objeto de autenticación
     * 
     * @param array $auth
     * @return array
     */
    protected function buildAuth(array $auth): array
    {
        if ($auth['type'] === 'bearer') {
            return [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => $auth['token'] ?? '{{token}}',
                        'type' => 'string',
                    ],
                ],
            ];
        }

        // Basic auth
        if ($auth['type'] === 'basic') {
            return [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => $auth['username'] ?? '', 'type' => 'string'],
                    ['key' => 'password', 'value' => $auth['password'] ?? '', 'type' => 'string'],
                ],
            ];
        }

        return ['type' => 'noauth'];
    }

    /**
     * Genera la colección final como array
     * 
     * @return array
     */
    public function build(): array
    {
        // Añadir carpetas al item principal
        foreach ($this->folders as $folder) {
            $this->collection['item'][] = $folder;
        }

        return $this->collection;
    }

    /**
     * Genera la colección como JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Genera un UUID v4
     * 
     * @return string
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
