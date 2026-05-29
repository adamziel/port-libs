# SQLite attach TEMP WAL schema cache current-source next301-316

Prepares the direct follow-on to the merged next285-300 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext301316()`;
- covers another larger 16-slot attach/TEMP/WAL schema-cache batch with committed main, attached queue, and newly attached campaign WAL schema-cookie publication, TEMP shadow index rename, TEMP and attached table removal, ATTACH/DETACH search-order churn, indexed writer retry blocking, active current-source readers, and ignored uncommitted WAL frames;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext301316Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next301-316.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext301316Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next301-316.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next285-300 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next301-316 attach/TEMP/WAL schema-cache mutations after the accepted next285-300 chain.
