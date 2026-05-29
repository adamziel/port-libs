# SQLite attach TEMP WAL schema cache current-source next133-136

Prepares the next attach/TEMP/WAL schema-cache handoff after next129-132:

- next133 covers a qualified reader prepared before a later `ATTACH` supplies the schema and table;
- next134 covers `DETACH` expiring active attached-schema snapshots only after the current step;
- next135 covers attached-schema `REINDEX`-style index rename invalidating `INDEXED BY` statements;
- next136 covers committed WAL schema-cookie/index writes blocking stale write retries while rolled-back TEMP schema noise is ignored.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext133136Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next129-132.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next133-136.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext133136Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next129-132.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next133-136.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across late ATTACH availability, DETACH removal, attached index rename, active statement lifecycle, and committed WAL write invalidation.
