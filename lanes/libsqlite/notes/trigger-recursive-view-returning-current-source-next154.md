# Trigger Recursive View RETURNING Current Source Next154

## Behavior

Adds canonical current-source drain fencing for recursive view trigger `RETURNING`
streams: next-source rows may be planned and written, but they are not visible
to the next cursor until every current-source `RETURNING` row is acknowledged.

## Verification

- Red-first command before implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext154Test.php`
  failed because the focused test file and canonical method did not exist.
- Focused command after implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext154Test.php`
- Family command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturning*Test.php`
- Example smoke:
  `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next154.php --self-test`

## Dependency Closure

No new support component is needed. The slice reuses native recursive trigger
RETURNING current-source savepoint behavior and adds only cursor handoff/drain
metadata in the canonical trigger recursive view plan.

## Non-Overlap

Avoids suite-evidence current-next root-gate metadata, window GROUPS/RANGE,
row-value RETURNING windows, JSON table, WAL, VFS, B-tree, and numbered
production suffix consolidation surfaces.
