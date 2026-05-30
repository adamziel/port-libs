# SQLite attach TEMP WAL schema cache final preparation window

Extends the next973-988 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::finalSchemaCachePreparationWindow()`.

- next989-1004 keeps the consolidated attach schema-cache planner and records the new dependency range before the next973-988 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next1004, TEMP schema writes, attached index rename expiry, attached table drop expiry, detached review schema removal, attached seal schema visibility, publish WAL movement, uncommitted WAL filtering, and stable handoff metadata lookup preservation.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalHandoffWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPreparationWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-handoff-window.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-preparation-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalHandoffWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPreparationWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-handoff-window.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-preparation-window.php --self-test
git diff --check
```
