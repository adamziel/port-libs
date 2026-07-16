# Trigger RETURNING Method Consolidation Thirty-Third Pass

Consolidated the trigger recursive-view RETURNING current-source yield-watermark production entry point from the numbered `executeNext206()` wrapper into the stable `executeYieldWatermarkFence()` method on `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.

The pass also renamed the direct private `*Next206()` helper methods to descriptive yield-watermark helper names and migrated the direct focused test and Application smoke caller. Result payload keys, option names, status strings, dependency markers, and assertions remain unchanged so existing coverage continues to verify the same behavior while production method names no longer carry the worker number.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next206.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext206Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next206.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production method-name consolidation over the existing native trigger RETURNING yield-watermark behavior.
