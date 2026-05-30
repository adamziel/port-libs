# SQLite attach TEMP WAL schema cache current-source next957-972

Extends the next941-956 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCachePublishHandoffWindow()`.

- next957-972 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next941-956 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next972, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached review schema visibility, publish WAL movement, detached audit schema removal, and stable report metadata lookup preservation.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishHandoffWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-publish-handoff-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReportWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishHandoffWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-publish-handoff-window.php --self-test
git diff --check
```
