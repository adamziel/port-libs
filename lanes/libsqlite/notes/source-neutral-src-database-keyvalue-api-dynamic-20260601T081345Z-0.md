# source-neutral-src-database-keyvalue-api-dynamic-20260601T081345Z-0

## Scope

- Removed the remaining mixed-case domain-specific private helper name from the owned `SQLiteDatabase.php` key-value write-index path:
  - `wordPressWriteIndexColumns()` is now `applicationWriteIndexColumns()`.
- Tightened `SQLiteNoDomainSpecificApiTest.php` so future key-value source and declaration scans reject the same mixed-case domain spelling.
- No public result keys or behavior changed; existing generic `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy` API names are preserved.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDatabase.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed, `1 test files, 5 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php`: passed, `2 test files, 68 assertions, 0 failures`.
- Example smoke: not run; no example was added or updated in this slice.
- Root harness: not run; isolated micro-slice.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This is a source-neutral key-value API name cleanup and guard tightening over existing native PHP `SQLiteDatabase` and key-value row helpers.

## Non-Overlap

This slice does not add upstream PASS rows or change counters. It removes production-source key-value domain debt that was not covered by the previous source-neutral cursor/range helper cleanup.
