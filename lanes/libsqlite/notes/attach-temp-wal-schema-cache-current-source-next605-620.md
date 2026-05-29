# SQLite attach/temp/WAL schema-cache current-source next605-620

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` with `currentSourceNext605620()` as the direct follow-on to integrated next589-604. The slice starts from the next589-604 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, attached audit schema visibility, publish detach expiry, archive table expiry, queue handoff rename expiry, and handoff index expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext605620Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next605-620.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext605620Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next605-620.php --self-test`
- `git diff --check`
