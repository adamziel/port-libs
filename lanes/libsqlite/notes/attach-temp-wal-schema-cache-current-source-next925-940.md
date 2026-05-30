# SQLite attach TEMP WAL schema cache current-source next925-940

Extends the next909-924 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCacheAuditWindow()`.

- next925-940 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next909-924 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next940, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached archive schema visibility, publish WAL movement, and detached-schema removal.
- The detached transient archive remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheMetricsWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-audit-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheMetricsWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheAuditWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-audit-window.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
