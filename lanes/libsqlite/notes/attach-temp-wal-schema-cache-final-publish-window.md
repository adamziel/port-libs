# ATTACH Temp WAL Schema Cache Final Publish Window

Extends the consolidated attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCachePlan::finalSchemaCachePublishWindow()`.

Behavior: carries prior dependency receipts, verifies main WAL cookie advance, temp schema cookie advance, attached handoff table rename expiry, queue index drop expiry, archive detach removal, seal WAL visibility, attached review schema visibility, active current snapshot preservation, writer retry blocking, and stable publish metadata lookup preservation.

Consolidation: direct test and Application example fixtures use stable descriptive table, index, and file names. No generated numbered suffix names remain in this final publish-window surface.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPublishWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-publish-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalPublishWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-publish-window.php --self-test
git diff --check -- lanes/libsqlite
```
