# SQLite attach TEMP WAL schema cache current-source next381-396

Prepares the direct follow-on to the merged next365-380 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext381396()`;
- adds focused coverage for next381-396 dependencies, schema-cookie carry-forward, detached attached-schema statement expiry, TEMP index rename expiry, and ignored uncommitted WAL churn;
- adds a Application-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext381396Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next381-396.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext381396Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next381-396.php --self-test
git diff --check
```

Non-overlap: this follows next365-380 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next381-396 attach/TEMP/WAL schema-cache mutations after the accepted next365-380 chain.
