# SQLite row-value conflict savepoint RETURNING current-source next143

## Behavior

- Adds `SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan` for a row-value `UPDATE ... RETURNING` import batch that yields earlier rows, hits a later `OR ABORT` unique conflict inside the same savepoint, rolls back to the savepoint image, and retries against the restored current source.
- Covers SQLite-compatible savepoint visibility:
  - earlier `RETURNING` streams are observable before `ROLLBACK TO` but are discarded by the rollback plan;
  - the failed statement sees the current attempted source after prior successful statements;
  - retry statements run from the savepoint image, not from the failed attempted source.
- Adds a Application copied `wp_options` smoke for option rekeying and transient cleanup during a retryable import savepoint.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueConflictSavepointReturningCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

## Non-Overlap

This avoids accepted row-value next138 mixed `IGNORE`/`REPLACE`/`ROLLBACK` conflict algorithms, next140 `OR ABORT` statement-only preservation, next141 row-value delete/update savepoint behavior, trigger RETURNING savepoint clusters, and pager/WAL savepoint byte/application clusters. The new surface is the current-source handoff across `ROLLBACK TO savepoint` followed by retry after a row-value `UPDATE ... RETURNING` conflict.

## Dependency Closure

No new support component is needed. The slice composes existing native PHP row-value `UPDATE/DELETE ... RETURNING`, unique-conflict detection, and savepoint current-source diagnostics.
