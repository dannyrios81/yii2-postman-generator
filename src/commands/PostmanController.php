<?php

namespace iguazoft\postman\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use iguazoft\postman\PostmanGenerator;

/**
 * Comando para generar colecciones de Postman desde anotaciones PHPDoc
 * 
 * Uso:
 * ```
 * php yii postman/generate
 * php yii postman/generate --output=docs/api.json
 * php yii postman/generate --dirs=@app/controllers,@app/modules/api/controllers
 * ```
 */
class PostmanController extends Controller
{
    /**
     * @var string Archivo de salida (puede usar aliases de Yii)
     */
    public $output = '@app/docs/api.postman_collection.json';

    /**
     * @var string Directorios a escanear, separados por coma
     */
    public $dirs = '@app/controllers';

    /**
     * @var string Nombre de la colección
     */
    public $name = 'API Collection';

    /**
     * @var string URL base
     */
    public $baseUrl = 'http://localhost';

    /**
     * @var bool Incluir endpoints marcados con @Postman\Hidden
     */
    public $includeHidden = false;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'output',
            'dirs',
            'name',
            'baseUrl',
            'includeHidden',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'o' => 'output',
            'd' => 'dirs',
            'n' => 'name',
            'u' => 'baseUrl',
            'a' => 'includeHidden',
        ]);
    }

    /**
     * Genera una colección de Postman desde anotaciones PHPDoc
     * 
     * @return int
     */
    public function actionGenerate(): int
    {
        $this->stdout("Generando colección Postman...\n", Console::FG_CYAN);

        $scanDirs = array_map('trim', explode(',', $this->dirs));

        $generator = new PostmanGenerator([
            'scanDirs' => $scanDirs,
            'collectionName' => $this->name,
            'variables' => [
                'baseUrl' => $this->baseUrl,
            ],
            'includeHidden' => $this->includeHidden,
        ]);

        $outputPath = Yii::getAlias($this->output);

        $this->stdout("Escaneando directorios:\n", Console::FG_YELLOW);
        foreach ($scanDirs as $dir) {
            $this->stdout("  - " . Yii::getAlias($dir) . "\n");
        }

        if ($generator->generate($this->output)) {
            $this->stdout("\n✓ Colección generada exitosamente:\n", Console::FG_GREEN);
            $this->stdout("  " . $outputPath . "\n\n");
            return ExitCode::OK;
        }

        $this->stderr("✗ Error al generar la colección\n", Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Muestra ayuda sobre las anotaciones disponibles
     * 
     * @return int
     */
    public function actionHelp(): int
    {
        $this->stdout("\n");
        $this->stdout("=== Yii2 Postman Generator ===\n", Console::FG_CYAN, Console::BOLD);
        $this->stdout("\n");

        $this->stdout("Anotaciones de Clase:\n", Console::FG_YELLOW);
        $this->stdout("  @Postman\\Collection(\"Nombre\")      - Nombre de la colección\n");
        $this->stdout("  @Postman\\Description(\"Desc\")       - Descripción de la colección\n");
        $this->stdout("  @Postman\\Variable(\"key\", \"value\")  - Variable de la colección\n");
        $this->stdout("\n");

        $this->stdout("Anotaciones de Método:\n", Console::FG_YELLOW);
        $this->stdout("  @Postman\\Request(\"Nombre\")         - Nombre del request (requerido)\n");
        $this->stdout("  @Postman\\Folder(\"Carpeta\")         - Carpeta donde agrupar\n");
        $this->stdout("  @Postman\\Method(\"POST\")            - Método HTTP (GET, POST, PUT, DELETE)\n");
        $this->stdout("  @Postman\\Path(\"/api/endpoint\")     - Ruta del endpoint\n");
        $this->stdout("  @Postman\\Description(\"Desc\")       - Descripción del request\n");
        $this->stdout("  @Postman\\Auth(\"bearer\", \"{{token}}\") - Autenticación\n");
        $this->stdout("  @Postman\\Body({...})               - Body JSON del request\n");
        $this->stdout("  @Postman\\Header(\"key\", \"value\")    - Header personalizado\n");
        $this->stdout("  @Postman\\Hidden                     - Ocultar endpoint (no incluir por defecto)\n");
        $this->stdout("\n");

        $this->stdout("Opciones de Comando:\n", Console::FG_YELLOW);
        $this->stdout("  --include-hidden, -a                - Incluir endpoints marcados como @Postman\\Hidden\n");
        $this->stdout("\n");

        $this->stdout("Ejemplo:\n", Console::FG_GREEN);
        $this->stdout("/**\n");
        $this->stdout(" * @Postman\\Request(\"Crear Usuario\")\n");
        $this->stdout(" * @Postman\\Folder(\"Usuarios\")\n");
        $this->stdout(" * @Postman\\Method(\"POST\")\n");
        $this->stdout(" * @Postman\\Path(\"/api/users\")\n");
        $this->stdout(" * @Postman\\Auth(\"bearer\", \"{{token}}\")\n");
        $this->stdout(" */\n");
        $this->stdout("public function actionCreate() { }\n");
        $this->stdout("\n");

        return ExitCode::OK;
    }
}
