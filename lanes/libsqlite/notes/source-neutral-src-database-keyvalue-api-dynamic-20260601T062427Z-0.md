# source-neutral-src-database-keyvalue-api-dynamic-20260601T062427Z-0

## Scope

- Neutralized the direct key-value cursor APIs in `SQLiteUtf16CollationAffinityCursor`, `SQLiteUtf16CollationAffinitySourceSwitchPlan`, and `SQLiteMalformedTextCurrentNextCursor`.
- Replaced option/autoload-shaped row keys and helper names with setting/key/load-policy terms:
  - `setting_id`
  - `key_name`
  - `key_value`
  - `key_value_bytes`
  - `load_policy`
- Updated the directly coupled UTF-16 collation/affinity, UTF-16 source-switch, and malformed-text tests/examples.
- Extended `SQLiteNoDomainSpecificApiTest.php` so these source, test, and example files are part of the key-value source-neutral guard.

## Verification

- `php -l` changed PHP files: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityCurrentSourceNext85Test.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinitySourceSwitchCurrentSourceNext100Test.php lanes/libsqlite/tests/SQLiteMalformedTextCurrentNext70Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 4 files / 152 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-utf16-collation-affinity-current-source-next85.php`: passed.
- `php lanes/libsqlite/examples/application-utf16-affinity-source-switch-current-source-next100.php`: passed.
- `php lanes/libsqlite/examples/application-malformed-text-current-next70.php --self-test`: passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This is a source-neutral API and fixture rename over existing bounded cursor behavior.

## Non-Overlap

This slice does not add upstream PASS rows or new SQLite behavior. It removes source-level key-value domain debt in the owned cursor/range helper surface and leaves counters unchanged.
