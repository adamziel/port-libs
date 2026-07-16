# rowvalue-update-delete-returning-window-current-source-next238

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE RETURNING window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Plan`. It layers a current-source/next-source pair fence over the accepted next235 RETURNING-window executor:

- discarded attempt rows are tagged as current-source window candidates;
- yielded retry rows are tagged as next-source window rows;
- rows are paired by `action:rowid` after rollback;
- overlapping pairs are classified as replayed after rollback;
- retry-only rows are classified as restart-only;
- attempt-only rows are classified as discarded-only;
- source and pair digests let a Application import smoke verify that stale attempt RETURNING rows cannot be yielded after rollback.

Application smoke: `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next238.php --self-test`

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Test.php
```

Expected dashboard movement: `phpPass +74` from the focused `TestRunner` PASS lines. Mapped upstream coverage remains unchanged; this is fresh focused executor behavior over already mapped row-value UPDATE/DELETE RETURNING and window primitives.

Non-overlap: avoids accepted nullable row-value savepoint cases, next232-next235 row-value RETURNING window materialization, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, and encoding clusters. The new behavior is specifically the current-source/next-source pair classification fence after rollback.

Dependency closure: no new support component is needed. The patch reuses lane-local row-value UPDATE/DELETE RETURNING SQL execution, savepoint rollback, and next235 RETURNING-window rows.

Next task: continue with a non-overlapping SQL executor/planner gap or row-value executor behavior not already covered by current-source RETURNING window fences.
