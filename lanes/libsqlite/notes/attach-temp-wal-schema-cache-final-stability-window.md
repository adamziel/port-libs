# SQLite attach TEMP WAL schema cache current-source final stability window

Extends the predecessor consolidated attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()`.

- final stability window keeps the same consolidated attach schema-cache planner and records the consolidated dependency range before the predecessor consolidated markers.
- The focused fixture covers committed main WAL schema-cookie movement through final_navigation, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema publication, and detached-schema removal.
- The detached scratch handoff remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalStabilityWindowTest.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-stability-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalLocalePublishTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalStabilityWindowTest.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-final-stability-window.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
