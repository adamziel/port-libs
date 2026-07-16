# SQLite attach/temp/WAL schema-cache current-source next653-668

Behavior: extends `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` with `currentSourceNext653668()` as the direct follow-on to integrated next637-652. The slice starts from the next637-652 handoff state, carries prior dependency receipts, and verifies active current snapshots, writer retry blocking, publish detach expiry, attached audit schema visibility, archive index expiry, queue handoff index rename expiry, and handoff meta table expiry.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext653668Test.php`
- `php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next653-668.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext653668Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext637652Test.php`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next653-668.php --self-test`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next637-652.php --self-test`
- `git diff --check`
