# SQLite attach TEMP WAL schema cache current-source next121-124

Prepares the next attach/TEMP/WAL schema-cache handoff after next118-120:

- next121 covers committed TEMP schema writes that create a shadowing table and expire unqualified main lookups;
- next122 covers TEMP table drops that restore the main lookup and expire TEMP-qualified statements;
- next123 covers `DETACH` while an attached-schema statement is active, preserving the current snapshot until reset;
- next124 covers table and index rename invalidation, including stale `INDEXED BY` write retry blocking.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext121124Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next121-124.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext118120Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext121124Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next118-120.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next121-124.php --self-test
git diff --check
```

Non-overlap: this stays within the existing attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, and SELECT planner clusters. The new behavior is specifically current-source schema-cache expiry across TEMP shadow create/drop, attached detach, and rename invalidation boundaries.
