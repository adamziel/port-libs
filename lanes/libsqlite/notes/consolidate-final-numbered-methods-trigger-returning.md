# Consolidate Final Numbered Trigger RETURNING Methods

## Scope

- Consolidated the final `executeNext229`, `executeNext230`, and `executeNext231` trigger recursive-view RETURNING production entry methods into descriptive methods on `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.
- Renamed the direct private helpers for those variants from numbered helper names to descriptive helper names.
- Migrated the direct trigger recursive-view RETURNING tests and WordPress examples for those variants to the descriptive entry methods.
- Fifteenth pass follow-up: consolidated the remaining direct `executeNext226`
  following-current seal entry method into `executeFollowingCurrentSeal()`,
  renamed its direct private helper methods to descriptive non-numbered names,
  and migrated the direct next226 test/example plus internal next230 caller.
- Forty-second pass follow-up: consolidated the direct numbered
  current-generation/depth fence entry method into
  `executeCurrentGenerationDepthFence()`, renamed its direct private helpers to
  descriptive non-numbered names, and migrated the direct test, WordPress
  example, and yield note to descriptive unsuffixed paths.
- Fifty-first pass follow-up: consolidated the direct numbered sealed
  next-source publication entry method into
  `executeSealedNextSourcePublication()`, renamed its direct private helpers to
  descriptive non-numbered names, and migrated the direct focused test and
  WordPress smoke to descriptive unsuffixed filenames.

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
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next226.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext226Test.php`
  - Result: `1 test files, 94 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next226.php --self-test`
  - Result: `wordpress-trigger-recursive-view-returning-current-source-next226 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentGenerationDepthFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-generation-depth-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentGenerationDepthFenceTest.php`
  - Result: `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-generation-depth-fence.php --self-test`
  - Result: `wordpress-trigger-recursive-view-returning-current-source-generation-depth-fence self-test passed`
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSealedNextSourcePublicationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-sealed-next-source-publication.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSealedNextSourcePublicationTest.php`
  - Result: `1 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-sealed-next-source-publication.php --self-test`
  - Result: `wordpress-trigger-recursive-view-returning-sealed-next-source-publication self-test passed`
- `git diff --check -- lanes/libsqlite`
- `rg -n "function\s+\w*(?:CurrentSource|Current)?Next[0-9]+|function\s+\w*Next[0-9]+" lanes/libsqlite/src | wc -l`
  - Result: `5728` remaining numbered production method lines.

## Dependency Closure

No new support component is needed. This consolidation reuses the existing native trigger recursive-view RETURNING implementation and changes only production method/helper names plus direct tests/examples.
