# SQLite attach TEMP WAL schema cache current-source next397-412

Prepares the direct follow-on to the merged next381-396 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext397412()`;
- adds focused coverage for next397-412 dependencies, schema-cookie carry-forward, detached attached-schema statement expiry, TEMP index rename expiry, dropped-table/index expiry, and ignored uncommitted WAL churn;
- adds a WordPress-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext397412Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next397-412.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext397412Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next397-412.php --self-test
git diff --check
```

Non-overlap: this follows next381-396 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next397-412 attach/TEMP/WAL schema-cache mutations after the accepted next381-396 chain.
