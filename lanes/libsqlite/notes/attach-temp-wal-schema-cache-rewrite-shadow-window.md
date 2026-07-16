# SQLite attach TEMP WAL schema cache rewrite shadow window

Consolidates the former numbered rewrite/shadow schema-cache direct coverage:

- keeps coverage on the canonical `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` entry point;
- removes the numbered direct test/example/note names from this attach-schema cache window;
- preserves coverage for schema-cookie carry-forward, TEMP index rename expiry, dropped attached index expiry, detached attached schema expiry, renamed attached table writer retry blocking, and ignored uncommitted WAL churn;
- keeps a Application-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRewriteShadowWindowTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-rewrite-shadow-window.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRewriteShadowWindowTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-rewrite-shadow-window.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this is an attach-schema consolidation-only cleanup and does not add behavior. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters.
