# Row-Value Savepoint Numbered Method Cleanup Sixty-Ninth Pass

Consolidated the direct row-value UPDATE/DELETE RETURNING savepoint batch
surface that still exposed generated numbered identifiers inside the canonical
production class. The batch now uses stable descriptive status, dependency,
savepoint, error, test, and WordPress smoke names while preserving the existing
behavior and direct coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-update-delete-returning-savepoint-current-source.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceTest.php` -> `1 test files, 62 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-rowvalue-update-delete-returning-savepoint-current-source.php --self-test` -> self-test passed.
- `git diff --check -- lanes/libsqlite` -> no whitespace errors.

Dependency closure: no new support component is needed; this pass reuses the
existing row-value UPDATE/DELETE RETURNING savepoint executor and only removes
generated numbered naming from the consolidated surface.
