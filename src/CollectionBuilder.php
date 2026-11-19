<?php

namespace iguazoft\postman;

/**
 * Builds a Postman Collection v2.1 document.
 *
 * The output also imports cleanly into Insomnia, Bruno, Hoppscotch and other clients
 * that read the Postman v2.1 format.
 */
class CollectionBuilder
{
    const SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    /** @var string */
    protected $name = 'API Collection';

    /** @var string */
    protected $description = '';

    /** @var array key => value */
    protected $variables = [];

    /** @var array Requests without folder */
    protected $items = [];

    /** @var array folder name => folder item */
    protected $folders = [];

    /** @var string|null */
    protected $postmanId;

    /**
     * Starts a new, empty collection.
     *
     * @param string $name
     * @param string $description
     * @param array $variables key => value
     * @return $this
     */
    public function init(string $name, string $description = '', array $variables = []): self
    {
        $this->name = $name;
        $this->description = $description;
        $this->variables = [];
        $this->items = [];
        $this->folders = [];
        $this->postmanId = null;

        return $this->addVariables($variables);
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Adds collection variables. Existing keys are kept unless $overwrite is true.
     *
     * @param array $variables key => value
     * @param bool $overwrite
     * @return $this
     */
    public function addVariables(array $variables, bool $overwrite = true): self
    {
        foreach ($variables as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($overwrite || !array_key_exists($key, $this->variables)) {
                $this->variables[$key] = (string) $value;
            }
        }

        return $this;
    }

    /**
     * Adds an empty folder (no-op when it already exists).
     *
     * @param string $name
     * @param string $description
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
     * Adds a request parsed by {@see AnnotationParser}.
     *
     * @param array $requestData
     * @return $this
     */
    public function addRequest(array $requestData): self
    {
        $request = $this->buildRequest($requestData);

        if (!empty($requestData['folder'])) {
            $this->addFolder($requestData['folder']);
            $this->folders[$requestData['folder']]['item'][] = $request;
        } else {
            $this->items[] = $request;
        }

        return $this;
    }

    /**
     * Returns the collection as an array. Can be called any number of times.
     *
     * @return array
     */
    public function build(): array
    {
        if ($this->postmanId === null) {
            $this->postmanId = $this->generateUuid($this->name);
        }

        $variables = $this->variables + ['baseUrl' => 'http://localhost', 'token' => ''];
        $variableList = [];
        foreach ($variables as $key => $value) {
            $variableList[] = ['key' => $key, 'value' => $value, 'type' => 'string'];
        }

        return [
            'info' => [
                '_postman_id' => $this->postmanId,
                'name' => $this->name,
                'description' => $this->description,
                'schema' => self::SCHEMA,
            ],
            'variable' => $variableList,
            'item' => array_merge($this->items, array_values($this->folders)),
        ];
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array $data
     * @return array
     */
    protected function buildRequest(array $data): array
    {
        $request = [
            'name' => $data['name'],
            'request' => [
                'method' => strtoupper(isset($data['method']) ? $data['method'] : 'GET'),
                'header' => [],
                'url' => $this->buildUrl(
                    isset($data['path']) ? (string) $data['path'] : '',
                    isset($data['queryParams']) ? $data['queryParams'] : []
                ),
            ],
            'response' => [],
        ];

        if (!empty($data['description'])) {
            $request['request']['description'] = $data['description'];
        }

        if (!empty($data['auth'])) {
            $request['request']['auth'] = $this->buildAuth($data['auth']);
        }

        if (isset($data['body']) && $data['body'] !== null) {
            $request['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $request['request']['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($data['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        if (!empty($data['tests'])) {
            $request['event'] = [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => TestScriptBuilder::build($data['tests']),
                ],
            ]];
        }

        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $key => $value) {
                $request['request']['header'][] = ['key' => (string) $key, 'value' => (string) $value];
            }
        }

        return $request;
    }

    /**
     * Builds the URL object. `:id` and `{id}` path segments become Postman path variables.
     *
     * @param string $path
     * @param array $queryParams key => value
     * @return array
     */
    protected function buildUrl(string $path, array $queryParams = []): array
    {
        $path = $path === '' ? '' : '/' . ltrim($path, '/');

        $segments = [];
        $pathVariables = [];
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            if (preg_match('/^\{(\w+)\}$/', $segment, $m)) {
                $segment = ':' . $m[1];
            }
            if ($segment[0] === ':') {
                $pathVariables[] = ['key' => substr($segment, 1), 'value' => ''];
            }
            $segments[] = $segment;
        }

        $raw = '{{baseUrl}}' . ($segments ? '/' . implode('/', $segments) : '');

        $url = [
            'raw' => $raw,
            'host' => ['{{baseUrl}}'],
            'path' => $segments,
        ];

        if ($queryParams) {
            $query = [];
            $pairs = [];
            foreach ($queryParams as $key => $value) {
                $query[] = ['key' => (string) $key, 'value' => (string) $value];
                $pairs[] = $key . '=' . $value;
            }
            $url['query'] = $query;
            $url['raw'] .= '?' . implode('&', $pairs);
        }

        if ($pathVariables) {
            $url['variable'] = $pathVariables;
        }

        return $url;
    }

    /**
     * @param array $auth
     * @return array
     */
    protected function buildAuth(array $auth): array
    {
        switch ($auth['type']) {
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'bearer' => [
                        ['key' => 'token', 'value' => isset($auth['token']) ? $auth['token'] : '{{token}}', 'type' => 'string'],
                    ],
                ];
            case 'basic':
                return [
                    'type' => 'basic',
                    'basic' => [
                        ['key' => 'username', 'value' => isset($auth['username']) ? $auth['username'] : '', 'type' => 'string'],
                        ['key' => 'password', 'value' => isset($auth['password']) ? $auth['password'] : '', 'type' => 'string'],
                    ],
                ];
            case 'apikey':
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        ['key' => 'key', 'value' => isset($auth['key']) ? $auth['key'] : 'X-Api-Key', 'type' => 'string'],
                        ['key' => 'value', 'value' => isset($auth['value']) ? $auth['value'] : '{{apiKey}}', 'type' => 'string'],
                        ['key' => 'in', 'value' => 'header', 'type' => 'string'],
                    ],
                ];
            default:
                return ['type' => 'noauth'];
        }
    }

    /**
     * Name-based UUID (v5 layout), so regenerating the same collection keeps the same id.
     * Postman then recognises a re-import as the same collection, and diffs in CI stay clean.
     *
     * @param string $name
     * @return string
     */
    protected function generateUuid(string $name): string
    {
        $bytes = substr(sha1('iguazoft/yii2-postman-generator:' . $name, true), 0, 16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
