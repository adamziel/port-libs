# SQLite attach TEMP WAL schema cache current-source next141-144

Prepares the next attach/TEMP/WAL schema-cache handoff after next137-140:

- next141 covers committed TEMP schema creation shadowing a previously prepared unqualified `main` reader;
- next142 covers `ALTER INDEX RENAME` invalidating a prepared `INDEXED BY` read plan;
- next143 covers attached-schema WAL commit cookies expiring active current snapshots only on reset;
- next144 covers `DETACH` of an attached Application archive schema, moving qualified statements to `__detached__` and blocking stale write retry before reprepare.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext137140Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext141144Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next137-140.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next141-144.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext137140Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext141144Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next137-140.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next141-144.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across committed TEMP shadow creation, `INDEXED BY` rename disappearance, attached WAL schema-cookie advancement, and attached schema detachment after the previously prepared drop-shadow/table-rename/drop-index/attached-index creation slices.
