# Trigger Recursive View RETURNING Current-Source Next170

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Plan`.
It composes the existing recursive `INSTEAD OF` view-trigger RETURNING cursor
model with a source-drain/reprepare barrier: current-source RETURNING rows stay
visible after the current view source is drained, while next-source rows are
held when the view/trigger schema cookie changes until the caller supplies the
matching reprepare token.

Application relevance: copied `wp_options` imports that route through recursive
views can preview yielded RETURNING rows without accidentally mixing rows from a
newly reparsed next view/trigger source into the current import cursor.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next170.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext170Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next170.php`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 60 assertions, 0 failures`.

Dependency closure: no new support component is needed; this reuses the native
recursive view RETURNING cursor/source model already present in the libsqlite
lane.
