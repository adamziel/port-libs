# SQLite trigger recursive view RETURNING current-source next169

## Behavior

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext169Plan`, a
bounded current-source RETURNING cursor composition over the accepted next165
recursive view model. It covers the re-entrant trigger boundary where an
INSTEAD OF recursive view trigger starts another current-source RETURNING
segment before staged next-source rows are visible.

The new behavior is the ordering rule: base current-source RETURNING rows drain,
then nested current-source RETURNING rows drain, and only then can staged
next-source rows become visible according to the savepoint release count.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext169Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next169.php`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext169Plan.php`
- Whitespace: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The implementation reuses the lane-local
recursive view RETURNING cursor queue and adds only re-entrant current-source
drain ordering.

## Non-Overlap

This avoids accepted next160/next162/next165 recursive view generation barriers,
FIFO queues, and cursor step visibility by adding a distinct nested
current-source drain between current and staged next-source cursor segments. It
also avoids accepted DML trigger RETURNING conflicts, deferred FK trigger work,
savepoint-trigger rollback, schema trigger/view invalidation, and row-value
RETURNING savepoint behavior.
