<?php

namespace iguazoft\postman\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use iguazoft\postman\PostmanGenerator;

/**
 * Generates Postman collections from `@Postman\...` PHPDoc annotations.
 *
 * ```
 * php yii postman/generate
 * php yii postman/generate --output=@app/docs/api.json
 * php yii postman/generate --dirs=@app/controllers,@app/modules/api/controllers
 * php yii postman/generate --name="My API" --base-url=https://api.example.com
 * ```
 */
class PostmanController extends Controller
{
    /**
     * @var string Output file (Yii aliases allowed)
     */
    public $output = '@app/docs/api.postman_collection.json';

    /**
     * @var string Comma separated directories to scan
     */
    public $dirs = '@app/controllers';

    /**
     * @var string|null Collection name. Defaults to `@Postman\Collection` or "API Collection"
     */
    public $name;

    /**
     * @var string|null Value of the `{{baseUrl}}` variable. Defaults to `@Postman\BaseUrl` or http://localhost
     */
    public $baseUrl;

    /**
     * @var bool Include actions annotated with `@Postman\Hidden`
     */
    public $includeHidden = false;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID === 'generate') {
            $options = array_merge($options, ['output', 'dirs', 'name', 'baseUrl', 'includeHidden']);
        }

        return $options;
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
     * Generates a Postman collection from PHPDoc annotations.
     *
     * @return int
     */
    public function actionGenerate(): int
    {
        $scanDirs = array_values(array_filter(array_map('trim', explode(',', $this->dirs))));

        $variables = [];
        if ($this->baseUrl !== null && $this->baseUrl !== '') {
            $variables['baseUrl'] = $this->baseUrl;
        }

        $generator = new PostmanGenerator([
            'scanDirs' => $scanDirs,
            'collectionName' => $this->name !== '' ? $this->name : null,
            'variables' => $variables,
            'includeHidden' => (bool) $this->includeHidden,
        ]);

        $this->stdout("Scanning:\n", Console::FG_YELLOW);
        foreach ($scanDirs as $dir) {
            $this->stdout('  - ' . Yii::getAlias($dir) . "\n");
        }

        try {
            $collection = $generator->generateArray();
        } catch (\iguazoft\postman\InvalidAnnotationException $e) {
            $this->stderr("✗ " . $e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }
        $count = $this->countRequests($collection['item']);

        if ($generator->generate($this->output)) {
            $this->stdout("\n✓ {$count} request(s) written to " . Yii::getAlias($this->output) . "\n", Console::FG_GREEN);
            if ($count === 0) {
                $this->stdout("  No action has a @Postman\\Request annotation. Run `php yii postman/help`.\n", Console::FG_YELLOW);
            }

            return ExitCode::OK;
        }

        $this->stderr("✗ Could not write the collection file\n", Console::FG_RED);

        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Shows the annotation reference.
     *
     * @return int
     */
    public function actionHelp(): int
    {
        $this->stdout("\n=== Yii2 Postman Generator ===\n\n", Console::FG_CYAN, Console::BOLD);

        $this->stdout("Class annotations:\n", Console::FG_YELLOW);
        $this->stdout("  @Postman\\Collection(\"Name\")              Collection name\n");
        $this->stdout("  @Postman\\Description(\"Text\")             Collection description\n");
        $this->stdout("  @Postman\\BaseUrl(\"https://api.test\")      Default {{baseUrl}}\n");
        $this->stdout("  @Postman\\Variable(\"key\", \"value\")        Collection variable (repeatable)\n\n");

        $this->stdout("Action annotations:\n", Console::FG_YELLOW);
        $this->stdout("  @Postman\\Request(\"Name\")                 Request name (required)\n");
        $this->stdout("  @Postman\\Method(\"POST\")                   HTTP method (default GET)\n");
        $this->stdout("  @Postman\\Path(\"/api/users/:id\")           Path; :id and {id} become path variables\n");
        $this->stdout("  @Postman\\Folder(\"Users\")                  Folder\n");
        $this->stdout("  @Postman\\Description(\"Text\")              Request description\n");
        $this->stdout("  @Postman\\Auth(\"bearer\", \"{{token}}\")       Bearer token\n");
        $this->stdout("  @Postman\\Auth(\"basic\", \"user\", \"pass\")     Basic auth\n");
        $this->stdout("  @Postman\\Auth(\"apikey\", \"X-Api-Key\", \"{{apiKey}}\")  API key header\n");
        $this->stdout("  @Postman\\Body({\"name\": \"John\"})          JSON body, may span several lines\n");
        $this->stdout("  @Postman\\Header(\"key\", \"value\")          Header (repeatable)\n");
        $this->stdout("  @Postman\\Query(\"key\", \"value\")           Query parameter (repeatable)\n");
        $this->stdout("  @Postman\\Hidden                         Skip unless --include-hidden\n");
        $this->stdout("  @Postman\\Test(\"status\", \"200\")          Test: status code (repeatable, also on the class)\n");
        $this->stdout("  @Postman\\Test(\"json\", \"data.id\")          Test: JSON property exists\n");
        $this->stdout("  @Postman\\Test(\"json\", \"data.ok\", \"true\")  Test: JSON property equals a value\n");
        $this->stdout("  @Postman\\Test(\"header\", \"X-Id\"[, \"v\"])   Test: header present (or equals)\n");
        $this->stdout("  @Postman\\Test(\"time\", \"500\")            Test: response time below N ms\n");
        $this->stdout("  @Postman\\Test(\"contains\", \"text\")        Test: body contains text\n\n");

        $this->stdout("Options of postman/generate:\n", Console::FG_YELLOW);
        $this->stdout("  --output, -o          Output file (default @app/docs/api.postman_collection.json)\n");
        $this->stdout("  --dirs, -d            Comma separated directories (default @app/controllers)\n");
        $this->stdout("  --name, -n            Collection name\n");
        $this->stdout("  --base-url, -u        {{baseUrl}} value\n");
        $this->stdout("  --include-hidden, -a  Include @Postman\\Hidden actions\n\n");

        return ExitCode::OK;
    }

    /**
     * @param array $items
     * @return int
     */
    private function countRequests(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $count += isset($item['request']) ? 1 : $this->countRequests(isset($item['item']) ? $item['item'] : []);
        }

        return $count;
    }
}
