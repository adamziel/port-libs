# Row-Value Savepoint Consolidation Sixty-Fifth Pass

Consolidated the nested savepoint RETURNING row-value family away from its old
worker-numbered production/test/example names and diagnostics.

- The old worker-numbered production class is now
  `SQLiteRowValueNestedSavepointReturningPlan`.
- Renamed the direct focused test to
  `SQLiteRowValueNestedSavepointReturningTest.php`.
- Renamed the WordPress smoke to
  `wordpress-rowvalue-nested-savepoint-returning.php`.
- Removed worker-number suffixes from the production status, dependencies,
  savepoint defaults, exception text, test names, and direct note references.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueNestedSavepointReturningPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-returning.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-returning.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this cleanup reuses the
native PHP row-value DML parser/executor, RETURNING projection, unique
constraint handling, and savepoint current-source planning.

Non-overlap: consolidation-only row-value savepoint cleanup; no WAL/VFS, JSON,
planner, trigger, B-tree, rowvalue-window, or behavior-counter surface changed.
