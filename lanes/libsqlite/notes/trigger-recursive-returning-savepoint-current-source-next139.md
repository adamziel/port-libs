# Trigger Recursive RETURNING Savepoint Current Source Next139

This slice adds `SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Plan`
for INSERT trigger recursion where RETURNING rows are yielded from the current
statement source before a `ROLLBACK TO` savepoint restores the prior image.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext139Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-trigger-recursive-returning-savepoint-current-source-next139.php`

Non-overlap:

- Avoids accepted next119 update-trigger/deferred-FK RETURNING savepoint
  behavior and next120 delete/FK savepoint behavior.
- Avoids accepted WAL byte truncation, VFS savepoint rollback application,
  grouped SELECT text, JSON table cursor/source, and B-tree page relocation
  clusters.

Dependency closure:

- No new support component is needed. The plan reuses the existing bounded
  `SQLiteDmlTriggerRecursionPlan` support and adds only the savepoint/RETURNING
  current-source wrapper needed for this behavior.
