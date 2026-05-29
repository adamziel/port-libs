# Trigger RETURNING Numbered Method Consolidation Thirty-Second Pass

Consolidated the first two trigger recursive view RETURNING production entry points in `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`:

- The initial insert-through-view source entry point is now `insertThroughViewSources()`.
- The recursive view source handoff entry point is now `executeRecursiveViewSourceHandoff()`.
- The matching private numbered helpers were renamed to stable descriptive helper names.

Direct tests and WordPress examples now call the canonical production methods. The returned payload/status keys remain unchanged so existing scenario assertions preserve behavior coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext143Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next143.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next157.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext143Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Test.php` -> `2 test files, 125 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next143.php --self-test`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next157.php --self-test`

Dependency closure: no new support component needed; this reuses the existing native trigger recursive view RETURNING/savepoint helpers.

Non-overlap: this is consolidation-only cleanup for trigger recursive view RETURNING method/helper names and does not add or repeat functional coverage.
