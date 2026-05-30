# Row-Value Inner FAIL Rollback Savepoint

## Scope

Documents the focused current-source behavior slice for nested row-value
`UPDATE`/`DELETE` statements with `RETURNING` inside savepoints:

- outer savepoint UPDATE/DELETE `RETURNING` rows remain yielded and current;
- inner savepoint UPDATE/DELETE rows are visible to a following `UPDATE OR FAIL`;
- `UPDATE OR FAIL` preserves prior successful rows at statement scope before the
  savepoint rollback;
- `ROLLBACK TO` the inner savepoint suppresses the inner and failed-statement
  `RETURNING` stream and restores the inner image;
- retry UPDATE/DELETE reads the outer current source, not the rolled-back inner
  or failed-statement source.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueInnerFailRollbackSavepointTest.php
```

Expected focused result after implementation:

```text
1 test files, 89 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-rowvalue-inner-fail-rollback-savepoint.php --self-test
```

## Non-Overlap

This avoids accepted preserved `OR FAIL` current-source retry behavior and
accepted released-inner rows discarded by outer rollback. The edge is
specifically an inner savepoint `ROLLBACK TO` after `OR FAIL`, where outer
savepoint changes survive but inner and failed-statement `RETURNING` streams
are suppressed.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP row-value
UPDATE/DELETE RETURNING executor, `OR FAIL` preservation path, and lane-local
savepoint current-source row images.
