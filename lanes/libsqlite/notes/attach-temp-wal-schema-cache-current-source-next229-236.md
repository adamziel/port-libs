# SQLite attach TEMP WAL schema cache current-source next229-236

Prepares the larger follow-on after the merged next221-228 chain:

- adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext229236()`;
- covers committed main WAL schema-cookie expiry, TEMP shadow removal, attached index rename, late ATTACH search-order append, explicit DETACH invalidation, attached table removal, ignored uncommitted WAL frames, and combined active-reader reset handling;
- keeps the slice inside the attach/TEMP/WAL schema-cache planner and reuses the existing current-source event normalization.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext229236Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next229-236.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext229236Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next229-236.php --self-test
git diff --check
```

Expected dashboard movement: focused PHP behavior only. No benchmark denominator change is expected because this reuses the lane-local attach schema-cache planner instead of admitting a new upstream inventory row.

Non-overlap: this follows next221-228 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is the current-source prepared statement lifecycle across next229-236 attach/TEMP/WAL schema-cache mutations after the previously merged chain.
