# SQLite row-value conflict RETURNING savepoint current-source next138

## Behavior

- Adds `SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan` for row-value `UPDATE ... RETURNING` statements executed inside a savepoint across `OR IGNORE`, `OR REPLACE`, and `OR ROLLBACK` unique-conflict algorithms.
- Covers SQLite-compatible current-source behavior:
  - `OR IGNORE` restores skipped conflicting rows and yields no `RETURNING` row for those skipped attempts.
  - `OR REPLACE` deletes the conflicting current row before yielding the replacement row, including chained replacement where a later row conflicts with a row replaced earlier in the same statement.
  - `OR ROLLBACK` aborts the savepoint transaction, restores the savepoint image, clears yielded streams, and reports the rollback conflict.
- Adds a WordPress copied `wp_options` smoke for import cleanup/rekeying where row-value assignments collide with existing `(blog_id, option_name)` keys.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueConflictReturningSavepointCurrentSourceNext138Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 69 assertions, 0 failures
```

## Non-Overlap

This avoids the accepted next132 `OR FAIL` partial-row RETURNING savepoint slice, next135 row-value DELETE/UPDATE savepoint chaining, deferred trigger/RETURNING savepoint slices, and the accepted WAL/pager savepoint application clusters. The new surface is mixed row-value UPDATE conflict algorithms (`IGNORE`/`REPLACE`/`ROLLBACK`) and their `RETURNING` visibility inside the current savepoint source.

## Dependency Closure

No new support component is needed. The slice composes existing native PHP row-value `UPDATE ... RETURNING` parsing/execution, unique-conflict modeling, and savepoint current-source diagnostics.
