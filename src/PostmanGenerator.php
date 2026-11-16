<?php

namespace iguazoft\postman;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

/**
 * Scans Yii2 controllers and builds a Postman collection from their `@Postman\...` annotations.
 *
 * ```php
 * $generator = new PostmanGenerator([
 *     'scanDirs' => ['@app/modules/api/v1/controllers'],
 * ]);
 * $generator->generate('@app/docs/api.postman_collection.json');
 * ```
 *
 * Precedence for the collection name, description and base URL:
 * explicit configuration > class annotations (`@Postman\Collection`, `@Postman\Description`,
 * `@Postman\BaseUrl`, first one found wins) > built-in defaults.
 */
class PostmanGenerator extends Component
{
    const DEFAULT_NAME = 'API Collection';
    const DEFAULT_BASE_URL = 'http://localhost';

    /**
     * @var string[] Directories to scan (Yii aliases allowed). Subdirectories are scanned too.
     */
    public $scanDirs = [];

    /**
     * @var string|null Collection name. Null = use `@Postman\Collection` or the default.
     */
    public $collectionName;

    /**
     * @var string|null Collection description. Null = use `@Postman\Description` or empty.
     */
    public $collectionDescription;

    /**
     * @var array Collection variables (key => value). A `baseUrl` key here overrides `@Postman\BaseUrl`.
     */
    public $variables = [];

    /**
     * @var bool Include actions annotated with `@Postman\Hidden`.
     */
    public $includeHidden = false;

    /** @var AnnotationParser */
    protected $parser;

    /** @var CollectionBuilder */
    protected $builder;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->parser = new AnnotationParser();
        $this->builder = new CollectionBuilder();
    }

    /**
     * Builds the collection and writes it to a file.
     *
     * @param string $outputPath File path, Yii aliases allowed
     * @return bool
     */
    public function generate(string $outputPath): bool
    {
        $json = $this->generateJson();

        $fullPath = Yii::getAlias($outputPath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        return file_put_contents($fullPath, $json) !== false;
    }

    /**
     * Builds the collection and returns it as a JSON string.
     *
     * @return string
     */
    public function generateJson(): string
    {
        return json_encode($this->generateArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Builds the collection and returns it as an array.
     *
     * @return array
     */
    public function generateArray(): array
    {
        $annotated = ['name' => null, 'description' => null, 'baseUrl' => null, 'variables' => []];
        $parsed = [];

        foreach ($this->findControllers() as $controllerClass) {
            try {
                $data = $this->parser->parseClass($controllerClass);
            } catch (\Throwable $e) {
                Yii::warning("Skipping {$controllerClass}: " . $e->getMessage(), __METHOD__);
                continue;
            }
            $parsed[] = $data;

            foreach (['name' => 'collection', 'description' => 'description', 'baseUrl' => 'baseUrl'] as $key => $source) {
                if ($annotated[$key] === null && !empty($data[$source])) {
                    $annotated[$key] = $data[$source];
                }
            }
            $annotated['variables'] += $data['variables'];
        }

        $variables = $this->variables;
        if (!isset($variables['baseUrl'])) {
            $variables['baseUrl'] = $annotated['baseUrl'] !== null ? $annotated['baseUrl'] : self::DEFAULT_BASE_URL;
        }

        $this->builder->init(
            $this->collectionName !== null ? $this->collectionName : ($annotated['name'] !== null ? $annotated['name'] : self::DEFAULT_NAME),
            $this->collectionDescription !== null ? $this->collectionDescription : (string) $annotated['description'],
            $variables
        );
        $this->builder->addVariables($annotated['variables'], false);

        foreach ($parsed as $data) {
            foreach ($data['requests'] as $request) {
                if (!empty($request['hidden']) && !$this->includeHidden) {
                    continue;
                }
                $this->builder->addRequest($request);
            }
        }

        return $this->builder->build();
    }

    /**
     * Returns the fully qualified class names of every `*Controller.php` file in {@see $scanDirs}.
     *
     * @return string[]
     */
    protected function findControllers(): array
    {
        $controllers = [];

        foreach ($this->scanDirs as $dir) {
            $path = Yii::getAlias($dir);

            if (!is_dir($path)) {
                Yii::warning("Directory not found: {$path}", __METHOD__);
                continue;
            }

            $files = FileHelper::findFiles($path, [
                'only' => ['*Controller.php'],
                'recursive' => true,
            ]);
            sort($files);

            foreach ($files as $file) {
                $className = $this->getClassNameFromFile($file);
                if ($className === null) {
                    continue;
                }
                if (class_exists($className)) {
                    $controllers[] = $className;
                } else {
                    Yii::warning("Class {$className} from {$file} is not autoloadable, skipped", __METHOD__);
                }
            }
        }

        return array_values(array_unique($controllers));
    }

    /**
     * Extracts the fully qualified name of the first class declared in a PHP file,
     * using the tokenizer so that the word "class" in comments or strings is ignored.
     *
     * @param string $file
     * @return string|null
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $tokens = token_get_all((string) file_get_contents($file));
        $namespace = '';
        $count = count($tokens);

        $nameTokens = [T_STRING, T_NS_SEPARATOR];
        if (defined('T_NAME_QUALIFIED')) {
            $nameTokens[] = T_NAME_QUALIFIED;
            $nameTokens[] = T_NAME_FULLY_QUALIFIED;
        }

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], $nameTokens, true)) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                }
                continue;
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip "Foo::class" and "new class"
                $prev = $i - 1;
                while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                    $prev--;
                }
                if ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_DOUBLE_COLON, T_NEW], true)) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        return ltrim($namespace . '\\' . $tokens[$j][1], '\\');
                    }
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    break;
                }
            }
        }

        return null;
    }
}
