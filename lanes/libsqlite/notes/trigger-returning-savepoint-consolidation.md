# Trigger Returning Savepoint Consolidation

## Delta

- Renamed the numbered trigger RETURNING savepoint helper to the canonical
  `SQLiteTriggerReturningSavepointPlan`.
- Renamed the internal signal class to `SQLiteTriggerReturningSavepointSignal`.
- Migrated the direct focused test and Application smoke to unsuffixed names.
- Replaced the numbered dependency marker with
  `sqlite-trigger-returning-savepoint`.

## Evidence

- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointTest.php`
- Example smoke: `php lanes/libsqlite/examples/application-trigger-returning-savepoint.php --self-test`
- PHP lint: changed PHP files.
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a production suffix/helper
consolidation only; it reuses the existing trigger RETURNING savepoint helper
behavior.
