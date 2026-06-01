# source-neutral-src-database-keyvalue-api-dynamic-20260601T153841Z-0

## Scope

- Neutralized two directly coupled `SQLiteDatabase` key-value row fixtures:
  - `SQLiteBTreePageSplitPointerMapCurrentNext34Test.php` now uses generic `primary_url` and `display_title` setting keys while preserving the same root split and pointer-map assertions.
  - `application-json-operator-json-quote-rhs-forms.php` now builds `app_settings` / `key_value` JSON operator indexes and reads `SQLiteKeyValueRow::$keyName`.
- Extended `SQLiteNoDomainSpecificApiTest.php` so both fixtures are covered by the key-value source-neutral guard.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteBTreePageSplitPointerMapCurrentNext34Test.php`: passed.
- `php -l lanes/libsqlite/examples/application-json-operator-json-quote-rhs-forms.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePageSplitPointerMapCurrentNext34Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed, `2 test files, 1457 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-operator-json-quote-rhs-forms.php`: passed and returned `module_json_quote_*_settings` matches for NULL, integer, and real `json_quote()` paths.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Root Harness

Not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This is a source-neutral fixture and example cleanup over existing native PHP `SQLiteDatabase` key-value row APIs.

## Non-Overlap

This slice does not add upstream PASS rows or change lane counters. It removes domain-shaped key-value fixture text and fixes a stale row-property access without touching accepted SQLite behavior clusters.
