# Yii2 Postman Generator

Extensión para Yii2 que genera colecciones de Postman automáticamente desde anotaciones PHPDoc.

## Instalación

### Vía Composer (recomendado)

```bash
composer require iguazoft/yii2-postman-generator
```

### Instalación local

Añadir al `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/yii2-postman-generator"
        }
    ],
    "require": {
        "iguazoft/yii2-postman-generator": "*"
    }
}
```

## Uso Rápido

### 1. Añadir anotaciones a tus controladores

```php
<?php

namespace app\controllers;

use yii\rest\Controller;

/**
 * @Postman\Collection("Mi API")
 * @Postman\Description("API REST de mi aplicación")
 */
class UserController extends Controller
{
    /**
     * @Postman\Request("Listar usuarios")
     * @Postman\Folder("Usuarios")
     * @Postman\Method("GET")
     * @Postman\Path("/api/users")
     * @Postman\Auth("bearer", "{{token}}")
     */
    public function actionIndex()
    {
        // ...
    }
    
    /**
     * @Postman\Request("Crear usuario")
     * @Postman\Folder("Usuarios")
     * @Postman\Method("POST")
     * @Postman\Path("/api/users")
     * @Postman\Auth("bearer", "{{token}}")
     * @Postman\Body({
     *     "name": "John Doe",
     *     "email": "john@example.com"
     * })
     */
    public function actionCreate()
    {
        // ...
    }
}
```

### 2. Generar la colección

```bash
php yii postman/generate
```

### 3. Opciones disponibles

```bash
# Especificar archivo de salida
php yii postman/generate --output=docs/api.json

# Escanear múltiples directorios
php yii postman/generate --dirs=@app/controllers,@app/modules/api/controllers

# Especificar nombre y URL base
php yii postman/generate --name="Mi API" --baseUrl=http://api.example.com

# Ver ayuda sobre anotaciones
php yii postman/help
```

## Anotaciones Disponibles

### Anotaciones de Clase

| Anotación | Descripción |
|-----------|-------------|
| `@Postman\Collection("nombre")` | Nombre de la colección |
| `@Postman\Description("desc")` | Descripción de la colección |
| `@Postman\BaseUrl("url")` | URL base por defecto |
| `@Postman\Variable("key", "value")` | Variable de la colección |

### Anotaciones de Método

| Anotación | Descripción | Requerido |
|-----------|-------------|-----------|
| `@Postman\Request("nombre")` | Nombre del request | ✅ Sí |
| `@Postman\Method("POST")` | Método HTTP | No (default: GET) |
| `@Postman\Path("/api/endpoint")` | Ruta del endpoint | No |
| `@Postman\Folder("Carpeta")` | Carpeta para agrupar | No |
| `@Postman\Description("desc")` | Descripción | No |
| `@Postman\Auth("bearer", "{{token}}")` | Autenticación | No |
| `@Postman\Body({...})` | Body JSON | No |
| `@Postman\Header("key", "value")` | Header HTTP | No |
| `@Postman\Query("key", "value")` | Query parameter | No |
| `@Postman\Hidden` | Ocultar endpoint | No |

## Ocultar Endpoints

Usa `@Postman\Hidden` para ocultar endpoints de la colección generada:

```php
/**
 * @Postman\Request("Endpoint Interno")
 * @Postman\Hidden
 */
public function actionInterno() { }
```

Por defecto, los endpoints con `@Postman\Hidden` **NO** se incluyen en la colección.

Para incluir **todos** los endpoints (incluyendo los ocultos):

```bash
php yii postman/generate --include-hidden
# o
php yii postman/generate -a
```

## Uso Programático

```php
use iguazoft\postman\PostmanGenerator;

$generator = new PostmanGenerator([
    'scanDirs' => [
        '@app/controllers',
        '@app/modules/api/v1/controllers',
    ],
    'collectionName' => 'Mi API',
    'variables' => [
        'baseUrl' => 'http://localhost:8080',
    ],
]);

// Guardar en archivo
$generator->generate('@app/docs/api.postman_collection.json');

// O obtener como JSON
$json = $generator->generateJson();
```

## Licencia

MIT
