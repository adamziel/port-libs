# Consolidate Final Numbered Trigger RETURNING Methods

## Scope

- Consolidated the final `executeNext229`, `executeNext230`, and `executeNext231` trigger recursive-view RETURNING production entry methods into descriptive methods on `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.
- Renamed the direct private helpers for those variants from numbered helper names to descriptive helper names.
- Migrated the direct trigger recursive-view RETURNING tests and WordPress examples for those variants to the descriptive entry methods.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext230Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next229.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next230.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next231.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext229Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext231Test.php`
  - Result: `3 test files, 269 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next229.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next230.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next231.php --self-test`
  - Result: all three self-tests passed.
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This consolidation reuses the existing native trigger recursive-view RETURNING implementation and changes only production method/helper names plus direct tests/examples.
