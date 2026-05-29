# SQLite attach TEMP WAL schema cache current-source next149-152

Prepares the next attach/TEMP/WAL schema-cache handoff after next145-148:

- next149 covers committed TEMP schema creation shadowing a previously prepared unqualified `main` options reader;
- next150 covers a committed `main` schema-cookie advance that removes a table while an active current-source reader finishes its snapshot before reset;
- next151 covers `ALTER INDEX RENAME` in TEMP expiring a prepared `INDEXED BY` read plan;
- next152 covers `DETACH` of an attached WordPress archive schema, moving qualified writers to `__detached__` and blocking stale retry before reprepare.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext145148Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext149152Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next145-148.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next149-152.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext145148Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext149152Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next145-148.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next149-152.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across TEMP shadow creation, committed table removal with active-reader reset handling, TEMP `INDEXED BY` rename disappearance, and attached schema detachment after the previous attach/drop/rename/detach slice.
