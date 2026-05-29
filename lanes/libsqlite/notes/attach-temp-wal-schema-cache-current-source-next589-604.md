# Attach/temp/WAL schema cache current-source next589-604

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext589604()` as the direct follow-on to integrated next573-588. The slice starts from the next573-588 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, temp index expiry, handoff index rename expiry, archive table expiry, publish schema attach visibility, and campaign detach expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext589604Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next589-604.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext589604Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next589-604.php --self-test`
- `git diff --check`
