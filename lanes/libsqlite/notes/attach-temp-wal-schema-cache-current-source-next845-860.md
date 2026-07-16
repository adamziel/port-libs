# SQLite attach TEMP WAL schema cache publish window

Extends the next829-844 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()`.

- The publish window keeps the same consolidated attach schema-cache planner and avoids a numbered production entrypoint.
- The focused fixture covers committed main WAL schema-cookie movement through next860, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema publication, and detached-schema removal.
- The detached scratch handoff remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-publish-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext829844Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-publish-window.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
