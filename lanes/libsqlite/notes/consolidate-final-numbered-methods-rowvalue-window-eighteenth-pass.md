# Row-value Window Numbered Method Consolidation Eighteenth Pass

Consolidated the row-value UPDATE/DELETE RETURNING window `executeNext462()` through `executeNext477()` production wrappers into the stable `executeWindowCurrentSourceContinuation()` entry point on `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.

The direct Application smoke now calls the stable continuation API for steps 462 through 477 while preserving the same candidate statuses, hashes, ready seals, and current-source throughput assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next462-477.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext462477Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext462477Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next462-477.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is production API consolidation over existing row-value/window behavior.

Non-overlap: this removes numbered row-value window production method wrappers only. It does not change row-value execution semantics, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, suite evidence, dashboard files, or root coordination files.
