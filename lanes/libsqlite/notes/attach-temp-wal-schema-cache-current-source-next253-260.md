# SQLite attach TEMP WAL schema cache current-source next253-260

Prepares the direct follow-on to the merged next245-252 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext253260()`;
- covers committed main WAL schema-cookie expiry, TEMP shadow removal revealing main, attached index rename, late ATTACH search-order append, explicit DETACH invalidation, attached rollup table removal, ignored uncommitted WAL frames, and combined active-reader reset handling;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext253260Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next253-260.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext253260Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next253-260.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next245-252 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next253-260 attach/TEMP/WAL schema-cache mutations after the accepted next245-252 chain.
