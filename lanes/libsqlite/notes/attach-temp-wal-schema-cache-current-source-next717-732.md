# SQLite attach/temp/WAL schema-cache current-source next717-732

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext717732()` as the direct follow-on to integrated next701-716. The slice starts from the next701-716 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, publish index rename expiry, review table expiry, report table rename expiry, attached audit and handoff schema visibility, temp schema cookie advance, and main WAL cookie advance.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext717732Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next717-732.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext717732Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext701716Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next717-732.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next701-716.php --self-test`
- `git diff --check`
