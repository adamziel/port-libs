# source-neutral-src-database-keyvalue-api-dynamic-20260601T214201Z-0

## Scope

- Audited the current `SQLiteDatabase.php` key-value row APIs plus directly coupled `SQLiteKeyValueRow*` helpers and range/cursor helpers.
- Production source in the owned surface already uses generic `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy` terminology.
- Hardened `SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php` so the dynamic key-value source guard now covers:
  - `SQLiteAffinityRangeCurrentSourceCursor`
  - `SQLiteEncodingCollationSourceCursor`
  - `SQLiteGlobCursor`
  - `SQLiteLikeCurrentNextCursor`
  - `SQLiteUtf16GlobCurrentNextCursor`
  - `SQLiteUtf16LikeGlobAffinityCurrentSourceCursor`
  - `SQLiteUtf16LikeGlobCurrentNextCursor`
- Extended `SQLiteNoDomainSpecificApiTest.php` to include those range/cursor helper source files in the key-value source scan.

## Dependency Closure

No new support component is needed. This slice only extends source-neutral guard coverage over existing native PHP key-value and cursor helpers.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 2 files / 19 assertions / 0 failures.
- Owned production-source forbidden-term scan over `SQLiteDatabase.php`, `SQLiteKeyValueRow*.php`, and the range/cursor helper files: no matches.

## Non-Overlap

This source-neutral cleanup does not add upstream PASS rows or new SQLite behavior. It does not overlap the accepted upstream-corpus, PDO, WAL, row-value, or insert/test throughput batches; counters remain unchanged.
