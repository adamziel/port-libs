# SQLite attach TEMP WAL schema cache current-source next269-284

Prepares the direct follow-on to the merged next261-268 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext269284()`;
- covers a larger 16-slot attach/TEMP/WAL schema-cache batch with committed main WAL expiry, TEMP shadow removal, indexed active-reader rename expiry, attached table removal, ATTACH search-order append, DETACH invalidation, attached WAL schema-cookie publish, and ignored uncommitted WAL frames;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext269284Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next269-284.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext269284Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next269-284.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next261-268 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next269-284 attach/TEMP/WAL schema-cache mutations after the accepted next261-268 chain.
