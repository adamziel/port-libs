# SQLite attach TEMP WAL schema cache current-source next509-524

Prepares the direct follow-on to merged next493-508:

- extends the established `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan` canonical source class with `currentSourceNext509524()`;
- avoids a new numbered source class because the local attach/TEMP/WAL schema-cache pattern keeps this domain in the existing next92 canonical planner;
- adds focused coverage for next509-524 dependencies, schema-cookie carry-forward, TEMP index rename expiry, dropped attached index expiry, detached attached schema expiry, renamed attached table writer retry blocking, and ignored uncommitted WAL churn;
- adds a WordPress-oriented self-test example for the same attach/TEMP/WAL schema-cache lifecycle.

Validation commands:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext509524Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next509-524.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext509524Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next509-524.php --self-test
git diff --check
```

Non-overlap: this follows next493-508 and stays inside attached schema-cache source planning. It avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters.
