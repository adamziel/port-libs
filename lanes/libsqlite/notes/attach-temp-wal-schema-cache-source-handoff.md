# SQLite attach TEMP WAL schema cache source handoff

Consolidates the attach/TEMP/WAL schema-cache source-handoff coverage onto
`SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()`:

- committed TEMP schema creation shadows a previously prepared unqualified
  `main` options reader;
- a committed `main` schema-cookie advance removes a table while an active
  current-source reader finishes its snapshot before reset;
- `ALTER INDEX RENAME` in TEMP expires a prepared `INDEXED BY` read plan;
- `DETACH` of an attached WordPress archive schema moves qualified writers to
  `__detached__` and blocks stale retry before reprepare.

Focused checks:

```text
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheSourceHandoffTest.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-source-handoff.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheSourceHandoffTest.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-source-handoff.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this stays inside the attach schema-cache planner and avoids
PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal,
WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding,
SELECT planner, and unrelated WAL hot-journal clusters. The consolidation
removes the generated numbered test/example/note surface while preserving the
same source-handoff schema-cache assertions.
