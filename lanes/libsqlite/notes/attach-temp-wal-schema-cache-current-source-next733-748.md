# SQLite attach/temp/WAL schema-cache current-source next733-748

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext733748()` as the direct successor to next717-732. The slice starts from the next717-732 handoff state, carries prior dependency receipts, and verifies main WAL cookie advance, temp schema cookie advance, queue index rename expiry, audit table drop expiry, handoff table rename expiry, publish WAL visibility, attached review schema visibility, archive detach removal, and stable report metadata lookup preservation.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext733748Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next733-748.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext733748Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext717732Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next733-748.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next717-732.php --self-test`
- `git diff --check`
