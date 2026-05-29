# SQLite attach/temp/WAL schema-cache current-source next685-700

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext685700()` as the direct follow-on to integrated next669-684. The slice starts from the next669-684 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, publish index rename expiry, handoff table expiry, audit detach expiry, attached archive and report schema visibility, queue WAL cookie advance, and main table rename expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext685700Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next685-700.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext685700Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext669684Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next685-700.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next669-684.php --self-test`
- `git diff --check`
