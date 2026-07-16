# SQLite attach TEMP WAL schema cache current-source next129-132

Prepares the next attach/TEMP/WAL schema-cache handoff after next125-128:

- next129 covers TEMP table rename changing unqualified statement resolution from `main` to `temp`;
- next130 covers attached-schema `DROP INDEX` invalidating a qualified `INDEXED BY` reader;
- next131 covers attached-table drop while an active snapshot can finish its current step before `SQLITE_SCHEMA` on reset;
- next132 covers a committed main WAL schema-cookie write blocking a stale write retry while rolled-back TEMP schema noise is ignored.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext125128Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next125-128.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next129-132.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext125128Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next125-128.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next129-132.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across TEMP rename search-order changes, attached index/table drops, active statement lifecycle, and committed WAL write invalidation.
