# Yii2 Postman Generator

[![tests](https://github.com/dannyrios81/yii2-postman-generator/actions/workflows/tests.yml/badge.svg)](https://github.com/dannyrios81/yii2-postman-generator/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/iguazoft/yii2-postman-generator/v/stable)](https://packagist.org/packages/iguazoft/yii2-postman-generator)
[![Total Downloads](https://poser.pugx.org/iguazoft/yii2-postman-generator/downloads)](https://packagist.org/packages/iguazoft/yii2-postman-generator)
[![License](https://poser.pugx.org/iguazoft/yii2-postman-generator/license)](LICENSE)

**Tu colección de Postman siempre sincronizada con tu API Yii2.** Anota las acciones de tus controladores, ejecuta un comando e importa el resultado en Postman, Insomnia, Bruno o Hoppscotch.

Sin especificación OpenAPI que escribir y mantener, sin dependencias extra: solo Yii2.

[Read in English](README.md)

```php
/**
 * @Postman\Request("Crear usuario")
 * @Postman\Folder("Usuarios")
 * @Postman\Method("POST")
 * @Postman\Path("/api/users")
 * @Postman\Auth("bearer", "{{token}}")
 * @Postman\Body({"name": "John Doe", "email": "john@example.com"})
 */
public function actionCreate() { /* ... */ }
```

```bash
$ php yii postman/generate
✓ 12 request(s) written to /var/www/app/docs/api.postman_collection.json
```

Ejemplo completo de salida: [`docs/example.postman_collection.json`](docs/example.postman_collection.json).

## Instalación

```bash
composer require --dev iguazoft/yii2-postman-generator
```

El comando `postman` se registra solo mediante el bootstrap de extensiones de Yii2.

## Uso

1. Anota los controladores (ver tabla abajo). Solo se exportan las acciones con `@Postman\Request`.
2. Genera: `php yii postman/generate`
3. Importa en Postman: **Import → File**.

| Opción | Alias | Por defecto | Descripción |
|---|---|---|---|
| `--output` | `-o` | `@app/docs/api.postman_collection.json` | Archivo de salida. Acepta alias de Yii. |
| `--dirs` | `-d` | `@app/controllers` | Directorios separados por coma, recursivos. |
| `--name` | `-n` | `@Postman\Collection` o `API Collection` | Nombre de la colección. |
| `--base-url` | `-u` | `@Postman\BaseUrl` o `http://localhost` | Valor de `{{baseUrl}}`. |
| `--include-hidden` | `-a` | `false` | Incluye acciones con `@Postman\Hidden`. |

## Anotaciones

**De clase:** `@Postman\Collection("Nombre")`, `@Postman\Description("Texto")`, `@Postman\BaseUrl("https://...")`, `@Postman\Variable("clave", "valor")` (repetible), `@Postman\Test(...)` (se aplica a todas las acciones del controlador).

**De acción:**

| Anotación | Descripción |
|---|---|
| `@Postman\Request("Nombre")` | **Obligatoria.** Nombre del request. |
| `@Postman\Method("POST")` | Método HTTP. Por defecto `GET`. |
| `@Postman\Path("/api/users/:id")` | Ruta. `:id` y `{id}` se vuelven variables de ruta. |
| `@Postman\Folder("Usuarios")` | Carpeta. |
| `@Postman\Description("Texto")` | Descripción. |
| `@Postman\Auth("bearer", "{{token}}")` | Bearer token. |
| `@Postman\Auth("basic", "{{user}}", "{{pass}}")` | Basic auth. |
| `@Postman\Auth("apikey", "X-Api-Key", "{{apiKey}}")` | API key en header. |
| `@Postman\Body({...})` | Body JSON, puede ocupar varias líneas. |
| `@Postman\Header("clave", "valor")` | Header. Repetible. |
| `@Postman\Query("clave", "valor")` | Parámetro de query. Repetible. |
| `@Postman\Hidden` | No se exporta salvo con `--include-hidden`. |
| `@Postman\Test(...)` | Assert en el script de tests. Repetible. Ver [Tests](#tests). |

### Tests

`@Postman\Test` agrega asserts al script **Tests** del request: la colección verifica la API, no solo la llama. Postman los ejecuta después de cada request y [Newman](https://github.com/postmanlabs/newman) los ejecuta en CI.

| Anotación | Assert generado |
|---|---|
| `@Postman\Test("status", "201")` | El status code es 201. |
| `@Postman\Test("json", "data.id")` | El JSON tiene `data.id` (ruta con puntos; `items[0].id` también sirve). |
| `@Postman\Test("json", "data.active", "true")` | ... y vale `true`. Un JSON válido (`true`, `42`, `null`, `"42"`, `[1,2]`) conserva su tipo; lo demás se compara como texto. |
| `@Postman\Test("header", "Content-Type")` | La respuesta trae el header. |
| `@Postman\Test("header", "X-Api-Version", "2")` | ... con ese valor exacto. |
| `@Postman\Test("time", "500")` | Responde en menos de 500 ms. |
| `@Postman\Test("contains", "created")` | El body contiene el texto. |

Los tests de la **clase** aplican a todas sus acciones y los de la acción se agregan después. Un `status` o `time` en la acción reemplaza el de la clase: el controlador espera `200` y su `actionCreate` espera `201`.

Un error de escritura (`@Postman\Test("statuss", "200")`) o un valor faltante detiene el comando con el nombre del controlador y la acción, y código de salida 65: el CI falla en vez de exportar la colección sin el test.

Ejecutar los tests en CI con Newman, con la API corriendo en el puerto 8080:

```yaml
- run: php yii postman/generate -o=@app/docs/api.postman_collection.json
- run: npx newman run docs/api.postman_collection.json --env-var "baseUrl=http://localhost:8080"
```

## Uso programático

```php
$generator = new \iguazoft\postman\PostmanGenerator([
    'scanDirs' => ['@app/controllers'],
    'variables' => ['baseUrl' => 'https://api.example.com'],
]);
$generator->generate('@app/docs/api.postman_collection.json');
```

## Licencia

MIT.
