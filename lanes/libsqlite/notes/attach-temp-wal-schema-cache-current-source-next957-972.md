# SQLite attach TEMP WAL schema cache current-source next957-972

Extends the next941-956 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext957972()`.

- next957-972 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next941-956 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next972, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached review schema visibility, publish WAL movement, detached audit schema removal, and stable report metadata lookup preservation.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext957972Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next957-972.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext941956Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext957972Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next957-972.php --self-test
git diff --check
```
