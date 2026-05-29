# SQLite attach/temp/WAL schema-cache current-source next669-684

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext669684()` as the direct follow-on to integrated next653-668. The slice starts from the next653-668 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, archive detach expiry, attached handoff and publish schema visibility, audit meta table expiry, queue WAL cookie advance, and main table rename expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext669684Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next669-684.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext669684Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext653668Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next669-684.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next653-668.php --self-test`
- `git diff --check`
