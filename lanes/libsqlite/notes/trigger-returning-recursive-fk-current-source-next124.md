# Trigger RETURNING Recursive FK Current Source Next124

Slice: `trigger-returning-recursive-fk-current-source-next124`

This adds a bounded current-source planner for recursive `DELETE ... RETURNING`
behavior where an `AFTER DELETE` trigger follows a linked parent row and
`ON DELETE CASCADE` removes copied Application metadata rows. The top-level
statement `RETURNING` rows are yielded from the current source, recursive
trigger deletes remain diagnostic, FK child/grandchild cascades are applied in
the attempted image, and `ROLLBACK TO` suppresses next-source visibility while
preserving attempted-yield evidence.

Non-overlap:

- Avoids accepted next121 deferred FK update rollback, next122 recursive
  savepoint UPSERT, next120 FK delete savepoint, next118 recursive UPSERT, and
  older trigger RETURNING savepoint helpers.
- Does not alter shared manifests or root coordination files.

Dependency closure:

- No new support component is required. The slice reuses lane-local row-array
  planning and adds the smallest trigger/FK current-source planner needed for
  this Application import behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveFkCurrentSourceNext124Test.php`
- `php lanes/libsqlite/examples/application-trigger-returning-recursive-fk-current-source-next124.php`
- `php -l lanes/libsqlite/src/SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveFkCurrentSourceNext124Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-returning-recursive-fk-current-source-next124.php`
- `git diff --check -- lanes/libsqlite`
