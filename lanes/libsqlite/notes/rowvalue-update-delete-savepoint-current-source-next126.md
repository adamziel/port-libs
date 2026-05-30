# Row-value UPDATE/DELETE savepoint current-source next126

Status: focused PHP behavior growth for `rowvalue-update-delete-savepoint-current-source-next126`.

This slice adds `SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan`, a bounded current-source DML planner that composes existing row-value `UPDATE` / `DELETE ... RETURNING` execution with savepoint rollback behavior. It records the savepoint image, applies a sequence of row-value DML statements, detects copied SQLite `UNIQUE(blog_id, option_name)` conflicts, rolls the current source back to the savepoint image, and keeps the attempted next-source image as diagnostic evidence.

Application smoke: `application-rowvalue-update-delete-savepoint-current-source-next126.php` models a copied multisite `wp_options` cleanup where a transient delete succeeds, the following row-value update would duplicate `(blog_id, option_name)`, and `ROLLBACK TO` restores the current source while preserving already yielded delete `RETURNING` rows and attempted update evidence.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNext126Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `50809` to `50864` from 55 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is fresh focused PHP behavior over already mapped row-value DML/savepoint primitives rather than a new upstream inventory unit.

Non-overlap: this avoids accepted row-value UPDATE/DELETE next110, row-value RETURNING next117, trigger/FK savepoint RETURNING next119/120/122/123, transaction savepoint trigger rollback next106, pager/VFS/WAL savepoint rollback/current-source clusters, grouped/ORDER/subquery SELECT SQL clusters, JSON table source/cursor/constraint clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new surface is row-value DML statement sequencing under a savepoint with uniqueness rollback restoring the current source while retaining attempted next-source diagnostics.

Dependency closure: no new support component is needed. The slice reuses lane-local row-value UPDATE/DELETE execution and savepoint current-source row-array modeling.

Next task: continue with deeper SQL executor/planner behavior or pager/VFS transaction application; avoid another row-value DML wrapper unless it applies a different current-source state transition.
