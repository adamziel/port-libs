# trigger-upsert-returning-savepoint-current-source-next142

## Behavior

Adds focused native PHP coverage for `INSERT ... ON CONFLICT DO NOTHING RETURNING`
inside a trigger/savepoint current-source handoff. Conflicting incoming rows fire
eligible `BEFORE INSERT` trigger diagnostics before the uniqueness check, but
the `DO NOTHING` conflict path produces no `RETURNING` row and does not increase
the change count. Released current-source inserts are visible to the next source;
rolled-back current-source inserts are suppressed and the next source starts
from the savepoint image.

This avoids the accepted trigger/UPSERT/RETURNING clusters that already cover
DO UPDATE, deferred FK rollback, recursive/view returning, row-value abort/fail
savepoints, and trigger view uniqueness behavior.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNext142Test.php`
  - `1 test files, 52 assertions, 0 failures`
  - `52` PASS lines
- Application smoke: `php lanes/libsqlite/examples/application-trigger-upsert-do-nothing-returning-current-source-next.php --self-test`
  - `application-trigger-upsert-do-nothing-returning-current-source-next self-test passed`

## Dependency Closure

No new support component is needed. The slice reuses native PHP trigger
dispatch, UPSERT conflict matching, RETURNING projection, and savepoint
current-source planning.

## Next

Keep trigger work away from accepted DO UPDATE/deferred/recursive/view clusters.
Useful follow-up would be parser-level SQL admission for the same
`ON CONFLICT DO NOTHING RETURNING` behavior once the text executor owns this
surface.
