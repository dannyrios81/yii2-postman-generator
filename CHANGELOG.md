# Changelog

## 1.2.0 - 2026-11-19

### Added
- `@Postman\Test("type", "value"[, "expected"])` generates the request's test script (`pm.test`). Types: `status`, `json`, `header`, `time`, `contains`. Repeatable, on actions and on the controller class (class tests apply to every action; an action `status` or `time` replaces the class one). Closes #1.

### Changed
- A malformed `@Postman\Test` throws `iguazoft\postman\InvalidAnnotationException` naming the controller and action. `postman/generate` prints it and exits with code 65 instead of exporting an incomplete collection. Other errors while reading a controller are still logged and the controller is skipped.

## 1.1.0 - 2026-11-16

### Fixed
- Requests from every controller are now kept. Previously, each controller with `@Postman\Collection` reset the collection, so only the last controller's requests were exported.
- Controllers whose doc comment contains the word "class" (for example "This class handles users") are no longer skipped. Class discovery now uses the PHP tokenizer.
- `@Postman\Query` is now exported to the request URL (`url.query` and `url.raw`).
- `@Postman\BaseUrl` now sets the `{{baseUrl}}` variable.
- Quoted arguments may contain parentheses, commas and apostrophes (`@Postman\Description("Returns users (paginated)")`).
- `@Postman\Auth("basic", "user", "pass")` now exports the credentials.
- `@Postman\Body` accepts nested objects and top-level arrays.
- `CollectionBuilder::build()` can be called more than once without duplicating folders.

### Added
- `@Postman\Auth("apikey", "X-Api-Key", "{{apiKey}}")`.
- Path variables: `/users/:id` and `/users/{id}` become Postman path variables.
- `PostmanGenerator::generateArray()`.
- The console command reports how many requests were exported.
- Stable collection id: regenerating the same collection keeps its `_postman_id`, so Postman re-imports it as the same collection and CI diffs stay clean.
- PHPUnit test suite and GitHub Actions CI on PHP 7.4 to 8.4.
- LICENSE file, English README.

### Changed
- Precedence: `--name`, `--base-url` and the `collectionName` / `variables` options now override class annotations. When they are not given, the first `@Postman\Collection`, `@Postman\Description` and `@Postman\BaseUrl` found are used.
- `@Postman\Variable` from several controllers are merged (first definition wins) instead of being discarded.

## 1.0.0 - 2025-12-10

- Initial release.
