# SQLite row-value UPDATE/DELETE RETURNING savepoint current-source next157

## Behavior

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Plan`, a lane-local current-source model for nested savepoints around row-value `UPDATE` / `DELETE ... RETURNING` statements.

The covered SQLite behavior is:

- outer savepoint statements yield and mutate the current source;
- inner savepoint statements may yield rows and mutate/delete rows;
- `ROLLBACK TO` the inner savepoint discards the inner `RETURNING` stream and restores the inner savepoint image;
- outer savepoint changes remain the current source;
- following statements continue from that restored current source.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Test.php`
  - `1 test files, 75 assertions, 0 failures`
  - 75 focused `PASS` lines
- `php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next157.php`
  - `application-rowvalue-update-delete-returning-savepoint-current-source-next157 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next157.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids the accepted row-value current-source clusters:

- next132 `OR FAIL` partial row-value `RETURNING` savepoint preservation;
- next140 statement `OR ABORT` savepoint preservation;
- next143 rollback-and-retry after conflict;
- next146 transaction rollback with `OR ROLLBACK`;
- next147 row-value `IS DISTINCT FROM` conflict/RETURNING behavior.

Next157 is specifically nested inner-savepoint rollback semantics: discard inner `RETURNING`, restore only the inner current source, preserve outer savepoint changes, then continue execution.

## Dependency Closure

No new support component is needed. The slice composes the existing native PHP `SQLiteUpdateDeleteReturningSql` executor with lane-local nested savepoint current-source bookkeeping.
