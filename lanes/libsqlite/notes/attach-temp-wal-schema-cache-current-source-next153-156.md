# SQLite attach TEMP WAL schema cache current-source next153-156

Prepares the next attach/TEMP/WAL schema-cache handoff after the consolidated
source-handoff coverage:

- next153 covers a committed `main` table drop expiring an unqualified Application termmeta reader;
- next154 covers an attached archive `DROP INDEX` invalidating a prepared `INDEXED BY` reader while the active current-source snapshot can finish before reset;
- next155 covers `ATTACH` of a new Application site schema changing a previously detached qualified reader into a retryable attached read;
- next156 covers a committed `main` WAL schema-cookie advance with a new options index, blocking a stale writer until reprepare.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheSourceHandoffTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext153156Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-source-handoff.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next153-156.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheSourceHandoffTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext153156Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-source-handoff.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next153-156.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across committed table removal, attached-schema index disappearance, new attached-schema resolution, and committed WAL cookie/index changes after the previous TEMP shadow/drop/index-rename/detach slice.
