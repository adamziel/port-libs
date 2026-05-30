# yield-trigger-recursive-view-returning-current-source-next186

Status: focused PHP behavior growth for recursive view trigger RETURNING current-source rebinding after rollback/reset.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan`. It builds on the accepted next183 reset-barrier behavior and models the next upstream boundary: after yielded recursive-view RETURNING rows are invalidated by a current-source rollback/reset, the following statement must bind a fresh post-reset current-source token/cursor and must not reuse the stale yielded RETURNING cursor rows.

Application smoke: `application-trigger-recursive-view-returning-current-source-next186.php` covers a copied `wp_options` recursive import view where a rollback invalidates yielded rows for `siteurl` and `current_plugin`, then a fresh current-source import statement returns only post-reset `siteurl` and `rewrite_rules` rows.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Plan.php

php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Test.php

php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next186.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next186.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext186Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next186.php
application-trigger-recursive-view-returning-current-source-next186 self-test passed
```

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING current-source reset modeling and adds post-reset cursor/source rebinding.

Non-overlap: this extends accepted next183 rollback/reset visibility by proving the following statement binds a fresh post-reset current source and discards stale yielded RETURNING rows. It avoids next180 snapshot admission, next182 generation fencing, next183 rollback invalidation, DELETE RETURNING, UPSERT, row-value, WAL, VFS, JSON, planner, and B-tree slices.
