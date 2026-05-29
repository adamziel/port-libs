# SQLite attach TEMP WAL schema cache current-source next317-332

Prepares the direct follow-on to the merged next301-316 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext317332()`;
- covers another larger 16-slot attach/TEMP/WAL schema-cache batch with committed main, analytics, queue, and campaign WAL schema-cookie publication, ignored uncommitted media WAL frames, TEMP shadow index rename and table removal, attached campaign and analytics table removal, ATTACH/DETACH search-order churn, detached audit/archive readers, and indexed writer retry blocking;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext317332Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next317-332.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext317332Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next317-332.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next301-316 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next317-332 attach/TEMP/WAL schema-cache mutations after the accepted next301-316 chain.
