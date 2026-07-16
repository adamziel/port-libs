# SQLite attach TEMP WAL schema cache final locale publish

Extends the predecessor attach/TEMP/WAL schema-cache handoff through the canonical `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` API.

- The final locale publish fixture keeps the same consolidated attach schema-cache planner and removes the worker-number suffix from the direct test, example, note, and fixture identifiers.
- The focused fixture covers committed main WAL schema-cookie movement through final publication, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema publication, and detached-schema removal.
- The detached scratch handoff remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalLocalePublishTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-locale-publish.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalLocalePublishTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheFinalStabilityWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-final-locale-publish.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
