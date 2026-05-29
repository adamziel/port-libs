# Row-Value UPDATE/DELETE RETURNING Savepoint Current Source Next201

## Behavior

Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext201Plan` for the current-source edge where:

- outer row-value `UPDATE ... RETURNING` statements remain committed in the surrounding transaction;
- successful savepoint-local row-value `UPDATE` and `DELETE ... RETURNING` streams are discarded by `ROLLBACK TO`;
- the savepoint remains active after `ROLLBACK TO`;
- retry statements read from the restored savepoint image, not from the discarded current source.

This is intentionally disjoint from accepted next200 `OR ABORT` statement-conflict handling: next201 has no failed conflict statement, and it models explicit `ROLLBACK TO` after successful yielded savepoint work.

## Verification

Focused commands run from the isolated worktree:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext201Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-rollback-to-savepoint-current-source-next201.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext201Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext201Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-rollback-to-savepoint-current-source-next201.php
git diff --check -- lanes/libsqlite
```

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-value `UPDATE`/`DELETE RETURNING` execution plus savepoint current-source image modeling.
