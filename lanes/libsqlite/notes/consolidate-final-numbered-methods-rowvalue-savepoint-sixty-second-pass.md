# Row-Value Savepoint Consolidation Sixty-Second Pass

Consolidated the row-value ignore nested retry savepoint surface away from
worker-numbered diagnostics.

- `executeIgnoreNestedRetrySavepointBatch()` now emits unsuffixed default
  savepoint names, status, dependencies, and exception text.
- Renamed the direct focused test to
  `SQLiteRowValueIgnoreNestedRetrySavepointTest.php`.
- Renamed the Application smoke to
  `application-rowvalue-ignore-nested-retry-savepoint.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueIgnoreNestedRetrySavepointTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-ignore-nested-retry-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueIgnoreNestedRetrySavepointTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-ignore-nested-retry-savepoint.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this cleanup reuses the
native PHP row-value UPDATE/DELETE RETURNING executor and savepoint
current-source row-image model.

Non-overlap: consolidation-only row-value savepoint cleanup; no WAL/VFS, JSON,
planner, trigger, B-tree, rowvalue-window, or behavior-counter surface changed.
