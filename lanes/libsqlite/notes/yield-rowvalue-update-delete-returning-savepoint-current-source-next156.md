# Row-Value UPDATE/DELETE RETURNING Savepoint Current Source Next156

## Behavior

This slice adds a bounded current-source savepoint plan for row-value
`UPDATE`/`DELETE ... RETURNING` yield behavior that was not covered by the
accepted FAIL/ROLLBACK/DISTINCT row-value savepoint clusters:

- `UPDATE OR IGNORE` yields only successfully changed rows and records ignored
  row-value unique conflicts without yielding them.
- `UPDATE OR REPLACE` deletes the conflicting row before yielding the replacing
  row.
- A following `DELETE ... RETURNING` reads from the current source produced by
  those row-value updates.
- `ROLLBACK TO` restores the savepoint image and discards attempted RETURNING
  streams while retaining diagnostics for caller recovery.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext156Test.php`
- Result: `1 test files, 74 assertions, 0 failures`
- New focused PASS-line delta: `+74`
- Application smoke: `php lanes/libsqlite/examples/application-rowvalue-yield-returning-savepoint-current-source-next156.php`

## Non-Overlap

Avoids accepted row-value `OR FAIL`, `OR ROLLBACK`, conflict DISTINCT, and
RETURNING savepoint clusters through next149. This next156 slice focuses on
`OR IGNORE`/`OR REPLACE` yield semantics and rollback-to stream discard after a
mixed UPDATE/DELETE sequence.

## Dependency Closure

No new support component is needed. The implementation composes existing
lane-local `SQLiteUpdateDeleteReturningSql` row-value execution and native PHP
savepoint current-source bookkeeping.
