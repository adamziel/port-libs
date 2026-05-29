# Trigger returning final numbered methods consolidation eighteenth pass

Consolidated the tail `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`
production entrypoints and private helpers for the next217, next218, next219,
next222, and next224 trigger RETURNING current-source fences into descriptive
unsuffixed method names. Direct tests and WordPress examples now call the
canonical method names. Serialized result keys and status strings were left
unchanged to preserve accepted behavior coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next217.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next218.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next219.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next222.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next224.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext218Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext222Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext224Test.php`
  - Result: `5 test files, 452 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next217.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next218.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next219.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next222.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next224.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the
existing canonical trigger RETURNING current-source implementation and only
removes numbered production method/helper names from the consolidated tail.
