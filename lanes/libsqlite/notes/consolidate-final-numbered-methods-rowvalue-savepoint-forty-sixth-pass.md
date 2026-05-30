# Row-Value Savepoint Numbered String Cleanup Forty-Sixth Pass

Timestamp: 2026-05-29T14:59:26Z

Scope:

- Consolidated the row-value DISTINCT RETURNING savepoint plan away from the
  remaining worker-numbered production diagnostics, default savepoint name,
  dependency tokens, and direct test labels.
- Kept the canonical production class and direct Application example in place;
  this pass only removes worker-number identifiers from the already canonical
  row-value savepoint behavior surface.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueSavepointReturningDistinctCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-savepoint-returning-distinct-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSavepointReturningDistinctCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-savepoint-returning-distinct-current-source-next.php --self-test`

Dependency closure:

- No new support component needed. This reuses the existing row-value
  UPDATE/DELETE RETURNING executor and savepoint current-source model.
