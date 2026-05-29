# SQLite attach TEMP WAL schema cache current-source next349-364

Prepares the direct follow-on to the merged next333-348 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext349364()`;
- covers another 16-slot attach/TEMP/WAL schema-cache batch with committed main, analytics, queue, and temp schema-cookie publication, ignored uncommitted staging WAL frames, TEMP index shadow churn, attached schema table/index removal and rename, ATTACH/DETACH search-order churn, detached audit/staging readers, and indexed writer retry blocking;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext349364Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next349-364.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext349364Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next349-364.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next333-348 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next349-364 attach/TEMP/WAL schema-cache mutations after the accepted next333-348 chain.
