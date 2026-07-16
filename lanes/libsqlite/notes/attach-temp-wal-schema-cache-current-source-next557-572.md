# Attach/temp/WAL schema cache current-source next557-572

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` with `currentSourceNext557572()` as the direct follow-on to integrated next541-556. The slice starts from the next541-556 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, temp table rename expiry, attached schema detach expiry, attached archive schema discovery, and stale audit table expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext557572Test.php`
- `php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next557-572.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext557572Test.php`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next557-572.php --self-test`
- `git diff --check`
