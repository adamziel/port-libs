# Trigger RETURNING Method Consolidation Twenty-Second Pass

Consolidated the trigger recursive-view RETURNING current-source drain-fence production entry point from the numbered `executeNext207()` wrapper into the stable `executeCurrentSourceDrainFence()` method on `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.

The pass also renamed the direct private `*Next207()` helper methods to descriptive drain-fence helper names and migrated the direct focused test and Application smoke caller. Result payload keys and status/dependency marker strings remain unchanged to preserve existing assertion coverage.

Verification results:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next207.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Test.php`: `1 test files, 97 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next207.php`: `application-trigger-recursive-view-returning-current-source-next207 self-test passed`.
- `git diff --check -- lanes/libsqlite`: passed.

Dependency closure: no new support component is needed; this is a production method-name consolidation over the existing native trigger RETURNING drain-fence behavior.
