# Trigger Recursive View RETURNING Current Source Next163

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Plan`, a current-source snapshot guard for recursive `INSTEAD OF` view triggers with `RETURNING`.
- Trigger-generated child rows are modeled as writes produced while `RETURNING` rows are drained, but they do not re-enter the current recursive view source because SQLite materializes the current statement source before trigger writes.
- When the next source generation is explicitly released, those generated rows can seed the next recursive source, preserving the current/next source boundary from next160 without duplicating the next160 visibility barrier.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next163.php`
- Syntax/diff checks: php-lint for changed PHP files and `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses lane-local recursive view `RETURNING`, current-source barrier, and trigger row projection behavior.

## Non-Overlap

Avoids accepted next160 source-generation visibility by adding the distinct snapshot/re-entry rule for trigger-generated rows. It does not change recursive row traversal, savepoint image rollback, trigger recursion depth, JSON table, B-tree, WAL, or VFS behavior.
