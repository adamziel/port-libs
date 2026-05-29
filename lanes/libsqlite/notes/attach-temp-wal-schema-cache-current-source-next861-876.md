# SQLite attach TEMP WAL schema cache rollout window

Extends the consolidated attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()`.

- The rollout window keeps the same consolidated attach schema-cache planner and avoids a numbered production entrypoint.
- The focused fixture covers committed main WAL schema-cookie movement through next876, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema rollout, publish WAL movement, and detached-schema removal.
- The detached transient rollout remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext845860Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next861-876.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext845860Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next861-876.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
