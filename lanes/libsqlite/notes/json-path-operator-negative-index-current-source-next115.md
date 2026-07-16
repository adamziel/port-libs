# JSON Path Operator Negative Index Current Source Next115

## Scope

- Added shared JSON operator RHS path normalization in `SQLiteJsonPath::normalizeOperatorPath()`.
- Reused that normalization from parser-level `SQLiteSelectExpression` execution and `SQLiteCreateIndex` JSON operator expression parsing.
- Covered SQLite 3.47-style negative integer RHS behavior for `->` / `->>` (`json -> -1`) over text JSON and JSONB copied `wp_options` rows.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathOperatorNegativeIndexCurrentSourceNext115Test.php`

Result:

`1 test files, 44 assertions, 0 failures`

Application smoke:

`php lanes/libsqlite/examples/application-json-path-operator-negative-index-current-source-next115.php`

Result: JSON output includes `plugin_cache_settings` with `last_rule=serve` / `last_channel=stable` and `plugin_forms_settings` with `last_rule=notify` / `last_channel=beta`.

## Non-Overlap

This avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, JSONB malformed path diagnostics, and generated-index JSONB operator transition planning. The slice is limited to current-source JSON path operator negative integer RHS normalization and execution.

## Dependency Closure

No new support component is needed. The slice reuses the existing native JSON/JSONB parser, SELECT expression evaluator, and CREATE INDEX parser.
