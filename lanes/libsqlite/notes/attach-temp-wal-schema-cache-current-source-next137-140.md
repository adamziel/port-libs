# SQLite attach TEMP WAL schema cache current-source next137-140

Prepares the next attach/TEMP/WAL schema-cache handoff after next133-136:

- next137 covers dropping a TEMP table shadow so an unqualified current-source reader falls through to `main`;
- next138 covers `ALTER TABLE RENAME` invalidating a qualified `main` reader whose current table source disappears;
- next139 covers `DROP INDEX` invalidating `INDEXED BY` and blocking stale write retries before reprepare;
- next140 covers attached-schema `CREATE INDEX` schema-cookie changes expiring active current snapshots only on reset.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext133136Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext137140Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next129-132.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next133-136.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next137-140.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext129132Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext133136Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext137140Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next129-132.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next133-136.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next137-140.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across TEMP shadow removal, table rename disappearance, `INDEXED BY` index removal, and attached-schema index creation after the previously prepared ATTACH/DETACH/index-rename/WAL invalidation slices.
