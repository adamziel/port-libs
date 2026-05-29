# SQLite attach/temp/WAL schema-cache current-source next701-716

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext701716()` as the direct follow-on to integrated next685-700. The slice starts from the next685-700 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, queue index rename expiry, archive table expiry, handoff detach expiry, report table rename expiry, attached review and metrics schema visibility, temp schema cookie advance, and main WAL cookie advance.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext701716Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next701-716.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext701716Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext685700Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next701-716.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next685-700.php --self-test`
- `git diff --check`
