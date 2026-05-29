# SQLite attach TEMP WAL schema cache current-source next493-508

Prepares the direct follow-on to the merged next477-492 chain:

- extends the established `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan` canonical source class with `currentSourceNext493508()`;
- avoids a new numbered source class because the local pattern keeps this attach/TEMP/WAL schema-cache domain in the existing next92 canonical planner;
- adds focused coverage for next493-508 dependencies, schema-cookie carry-forward, TEMP index rename expiry, dropped attached index expiry, detached attached schema expiry, renamed attached table writer retry blocking, and ignored uncommitted WAL churn;
- adds a WordPress-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext493508Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next493-508.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext493508Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next493-508.php --self-test
git diff --check
```

Non-overlap: this follows next477-492 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters.
