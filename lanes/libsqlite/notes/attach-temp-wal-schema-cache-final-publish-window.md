# ATTACH temp WAL schema cache current source next1005-1020

Extends the next989-1004 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::finalSchemaCachePublishWindow()`.

Behavior: carries prior dependency receipts, verifies main WAL cookie advance, temp schema cookie advance, attached handoff table rename expiry, queue index drop expiry, archive detach removal, seal WAL visibility, attached review schema visibility, active current snapshot preservation, writer retry blocking, and stable publish metadata lookup preservation.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPublishWindowTest.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-publish-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPreparationWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPublishWindowTest.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-publish-window.php --self-test
git diff --check
```
