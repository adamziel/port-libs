# Row-value UPDATE/DELETE dynamic LIMIT JSONB parity

## Scope

- Extended `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluation to accept JSONB-producing JSON scalar functions: `json()`, `jsonb()`, `json_array()`, `jsonb_array()`, `json_object()`, `jsonb_object()`, and `jsonb_extract()`.
- Preserved existing text JSON scalar behavior while allowing JSONB blob inputs through `json_extract()`, `json_array_length()`, `json_type()`, `json_valid(..., 8)`, and `json_error_position()`.
- Added row-value UPDATE and DELETE dynamic LIMIT/OFFSET parity coverage backed by upstream SQLite source files `/test/json102.test`, `/test/json105.test`, `/test/limit.test`, and `/test/rowvalue4.test`.

## Focused Evidence

- Before this slice: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 18595 assertions, 0 failures`
- After this slice: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 19297 assertions, 0 failures`
- Focused assertion delta: `+702`
- API guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` -> `No syntax errors detected`
  - `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` -> `No syntax errors detected`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "Valid JSON: $p\n";'`
  - `Valid JSON: lanes/libsqlite/lane-status.json`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

## Non-overlap

This slice does not repeat the accepted iif()/if() dynamic LIMIT forms, text JSON scalar LIMIT forms, json101 statement atomicity, row-value RETURNING order, or earlier UPDATE/DELETE LIMIT/OFFSET row-selection coverage. The new behavior is specifically JSONB-producing JSON scalar evaluation inside dynamic LIMIT/OFFSET expressions and row-value tuple-source subqueries.

## Dependency Closure

No new support component is needed. The implementation reuses the existing JSON canonicalization, JSON constructor, JSON extraction, JSON inspection, JSON validity, JSON error-position, and row-value UPDATE/DELETE LIMIT executor components.
