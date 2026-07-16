# SQLite attach TEMP WAL schema cache current-source next125-128

Prepares the next attach/TEMP/WAL schema-cache handoff after next121-124:

- next125 covers detach/reattach of the same attached schema name, where qualified statements keep the schema name but must refresh stale table/index cache entries;
- next126 covers attached WAL page-one schema-cookie advancement expiring qualified and unqualified readers that resolved into the attached schema;
- next127 covers TEMP `CREATE INDEX` making a previously missing `INDEXED BY` target visible and blocking stale write retry until reprepare;
- next128 covers rolled-back TEMP schema writes and WAL commits being ignored so they do not spuriously expire prepared statements.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext125128Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next125-128.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext121124Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext125128Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next121-124.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next125-128.php --self-test
git diff --check
```

Non-overlap: this stays inside the existing attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, and SELECT planner clusters. The new behavior is specifically current-source schema-cache expiry across reattached schemas, attached WAL cookie changes, TEMP index creation, and rollback-filtered schema-cache noise.
