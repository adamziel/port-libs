# SQLite attach/temp/WAL schema-cache current-source next621-636

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` with `currentSourceNext621636()` as the direct follow-on to integrated next605-620. The slice starts from the next605-620 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, audit detach expiry, attached review schema visibility, archive table expiry, queue handoff rename expiry, and handoff index expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext621636Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next621-636.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext621636Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next621-636.php --self-test`
- `git diff --check`
