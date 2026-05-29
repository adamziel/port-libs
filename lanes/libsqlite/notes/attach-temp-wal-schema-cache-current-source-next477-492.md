# SQLite attach TEMP WAL schema cache current-source next477-492

Prepares the direct follow-on to the merged next461-476 chain:

- extends the established `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` canonical source class with `currentSourceNext477492()`;
- avoids a new numbered source class because the local pattern keeps this attach/TEMP/WAL schema-cache domain in the existing next92 canonical planner;
- adds focused coverage for next477-492 dependencies, schema-cookie carry-forward, TEMP index rename expiry, dropped attached index expiry, attached schema detach expiry, renamed attached table writer retry blocking, and ignored uncommitted WAL churn;
- adds a WordPress-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext477492Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next477-492.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext477492Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next477-492.php --self-test
git diff --check
```

Non-overlap: this follows next461-476 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters.
