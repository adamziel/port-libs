# SQLite attach TEMP WAL schema cache current-source next941-956

Extends the next925-940 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCacheReportWindow()`.

- next941-956 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next925-940 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next956, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached review schema visibility, publish WAL movement, detached audit schema removal, and stable report metadata lookup preservation.
- The detached staging review remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReportWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-report-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReportWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-report-window.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
