# Attach/temp/WAL schema cache current-source next541-556

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` with `currentSourceNext541556()` as the direct follow-on to merged next525-540. The slice starts from the next525-540 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, temp index rename expiry, attached schema detach expiry, and newly attached audit schema discovery.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext541556Test.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next541-556.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext541556Test.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next541-556.php --self-test`
- `git diff --check`
