# SQLite attach TEMP WAL schema cache current-source next429-444

Prepares the direct follow-on to the merged next413-428 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext429444()`;
- adds focused coverage for next429-444 dependencies, schema-cookie carry-forward, attached-schema detach expiry, TEMP index rename expiry, dropped-table expiry, create-index cookie churn, and ignored uncommitted WAL churn;
- adds a WordPress-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext429444Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next429-444.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext429444Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next429-444.php --self-test
git diff --check
```

Non-overlap: this follows next413-428 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next429-444 attach/TEMP/WAL schema-cache mutations after the accepted next413-428 chain.
