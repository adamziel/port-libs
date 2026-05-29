# SQLite attach TEMP WAL schema cache final attach window

Extends the consolidated attach/TEMP/WAL schema-cache current-source planner in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::finalSchemaCacheAttachWindow()`.

- next1021-1036 records the new dependency range before the next1005-1020 predecessor markers without adding a numbered production class.
- The focused fixture covers TEMP table drop invalidation, attached index rename expiry, attached table rename expiry for an active writer, attached index drop expiry, committed WAL schema-cookie movement, DETACH removal, newly attached archive visibility, and uncommitted WAL filtering.
- The WordPress smoke models a staged import where temp shadow tables, review/publish/queue attached schemas, metrics WAL commits, audit DETACH, and archive ATTACH all participate in prepared-statement cache expiry before retry.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalAttachWindowTest.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-attach-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalAttachWindowTest.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-attach-window.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test delta: 27 assertions in 1 selected test file, 0 failures. Mapped upstream denominator coverage is unchanged because this is additional PHP behavior coverage over the existing ATTACH/TEMP/WAL schema-cache current-source inventory.

Non-overlap: avoids the accepted numbered-production consolidation, prior attach next1005-1020 behavior, pager/WAL checkpoint, rollback-journal, VFS writer/lock/sync, B-tree, JSON table, SQL planner, PRAGMA, trigger, encoding, and suite-runner surfaces. This patch only extends the canonical attach/temp schema-cache planner.

Dependency closure: no new support component is needed. The slice reuses lane-local ATTACH/DETACH, schema-write/WAL commit filtering, duplicate event consolidation, search-order resolution, and prepared-statement lifecycle expiry primitives.
