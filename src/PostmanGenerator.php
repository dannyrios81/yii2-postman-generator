<?php

namespace iguazoft\postman;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

/**
 * PostmanGenerator - Componente principal para generar colecciones Postman
 * 
 * Uso:
 * ```php
 * $generator = new PostmanGenerator();
 * $generator->scanDirs = ['@app/modules/api/v1/controllers'];
 * $generator->generate('docs/api.postman_collection.json');
 * ```
 */
class PostmanGenerator extends Component
{
    /**
     * @var array Directorios a escanear (aliases de Yii)
     */
    public $scanDirs = [];

    /**
     * @var string Nombre de la colección
     */
    public $collectionName = 'API Collection';

    /**
     * @var string Descripción de la colección
     */
    public $collectionDescription = '';

    /**
     * @var array Variables de la colección
     */
    public $variables = [];

    /**
     * @var bool Si es true, incluye también los endpoints marcados con @Postman\Hidden
     */
    public $includeHidden = false;

    /**
     * @var AnnotationParser
     */
    protected $parser;

    /**
     * @var CollectionBuilder
     */
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
     * Genera la colección Postman y la guarda en un archivo
     * 
     * @param string $outputPath Ruta de salida (puede usar aliases)
     * @return bool
     */
    public function generate(string $outputPath): bool
    {
        $controllers = $this->findControllers();

        // Inicializar builder
        $this->builder->init(
            $this->collectionName,
            $this->collectionDescription,
            $this->variables
        );

        // Procesar cada controlador
        foreach ($controllers as $controllerClass) {
            $this->processController($controllerClass);
        }

        // Guardar archivo
        $fullPath = Yii::getAlias($outputPath);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        return file_put_contents($fullPath, $this->builder->toJson()) !== false;
    }

    /**
     * Genera y retorna la colección como string JSON
     * 
     * @return string
     */
    public function generateJson(): string
    {
        $controllers = $this->findControllers();

        $this->builder->init(
            $this->collectionName,
            $this->collectionDescription,
            $this->variables
        );

        foreach ($controllers as $controllerClass) {
            $this->processController($controllerClass);
        }

        return $this->builder->toJson();
    }

    /**
     * Busca todos los controladores en los directorios configurados
     * 
     * @return array Lista de nombres de clases
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

            foreach ($files as $file) {
                $className = $this->getClassNameFromFile($file);
                if ($className) {
                    $controllers[] = $className;
                }
            }
        }

        return $controllers;
    }

    /**
     * Extrae el nombre de la clase de un archivo PHP
     * 
     * @param string $file Ruta al archivo
     * @return string|null
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        // Extraer namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        // Extraer nombre de clase
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Procesa un controlador y añade sus requests al builder
     * 
     * @param string $controllerClass
     */
    protected function processController(string $controllerClass): void
    {
        try {
            $data = $this->parser->parseClass($controllerClass);

            // Actualizar nombre de colección si está definido en anotaciones
            if (!empty($data['collection'])) {
                $this->builder->init(
                    $data['collection'],
                    $data['description'] ?? $this->collectionDescription,
                    array_merge($this->variables, $data['variables'] ?? [])
                );
            }

            // Añadir requests (filtrar los ocultos si no se incluyen)
            foreach ($data['requests'] as $request) {
                // Si el request está marcado como hidden y no se incluyen ocultos, saltar
                if (!empty($request['hidden']) && !$this->includeHidden) {
                    continue;
                }
                $this->builder->addRequest($request);
            }

        } catch (\Exception $e) {
            Yii::warning("Error processing controller {$controllerClass}: " . $e->getMessage(), __METHOD__);
        }
    }
}
