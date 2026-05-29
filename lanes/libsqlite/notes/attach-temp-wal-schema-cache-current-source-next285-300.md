# SQLite attach TEMP WAL schema cache current-source next285-300

Prepares the direct follow-on to the merged next269-284 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext285300()`;
- covers a larger 16-slot attach/TEMP/WAL schema-cache batch with committed main and attached WAL schema-cookie publication, TEMP shadow index rename, TEMP table removal, attached table removal, ATTACH/DETACH search-order churn, indexed writer retry blocking, active current-source readers, and ignored uncommitted WAL frames;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext285300Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next285-300.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext285300Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next285-300.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next269-284 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next285-300 attach/TEMP/WAL schema-cache mutations after the accepted next269-284 chain.
