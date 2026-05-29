# SQLite attach/temp/WAL schema-cache current-source next637-652

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext637652()` as the direct follow-on to integrated next621-636. The slice starts from the next621-636 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, review detach expiry, attached publish schema visibility, archive index expiry, queue handoff index rename expiry, and handoff table expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext637652Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next637-652.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext637652Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext621636Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next637-652.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next621-636.php --self-test`
- `git diff --check`
