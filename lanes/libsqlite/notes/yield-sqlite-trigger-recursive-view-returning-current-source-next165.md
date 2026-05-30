# SQLite trigger recursive view RETURNING current-source next165

## Behavior

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan`, a
small current-source cursor model composed over the accepted next162 recursive
view RETURNING queue. It records the RETURNING cursor `Next` sequence after an
INSTEAD OF recursive view trigger drains the current source, while staged next
source generations remain invisible until the savepoint releases them.

The covered behavior is intentionally narrower than accepted next160/next162:
those slices model generation barriers and a two-entry next-source queue; this
slice adds cursor-step ordering and visibility diagnostics for the current
source `Next` advancement across held, first-released, and all-released staged
generations.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next165.php`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext165Plan.php`
- Whitespace: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The implementation reuses native PHP
recursive view RETURNING current-source queue behavior from next162 and adds
bounded cursor visibility composition only.

## Non-Overlap

This does not repeat accepted trigger next160/next162 generation barrier/FIFO
queue behavior, row-value RETURNING savepoint behavior, deferred FK trigger
behavior, schema trigger/view invalidation, or DML trigger RETURNING conflicts.
The new assertions target current-source cursor `Next` ordering and staged
source visibility after current RETURNING rows drain.
