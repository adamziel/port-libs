# Attach Schema Consolidation Forty-Fifth Pass

Consolidated the attach temp/main WAL schema-cache direct caller surface that
still exposed a worker-number suffix in test, example, and note filenames and
test labels. The canonical production entry point remains
`SQLiteAttachTempMainWalSchemaCachePlan::currentNext()`.

Renamed direct files:

- `SQLiteAttachTempMainWalSchemaCacheTest.php`
- `wordpress-attach-temp-main-wal-schema-cache.php`
- `attach-temp-main-wal-schema-cache.md`

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteAttachTempMainWalSchemaCacheTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-main-wal-schema-cache.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempMainWalSchemaCacheTest.php`
  -> `1 test files, 70 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-attach-temp-main-wal-schema-cache.php --self-test`
- `git diff --check -- lanes/libsqlite`
- Exact user-named suffix audit across `lanes/libsqlite` -> no matches
- Attach temp/main WAL schema-cache removed-token audit -> no matches

Dependency closure: no new support component is needed; this is a
consolidation-only cleanup over existing attach/schema-cache behavior.
