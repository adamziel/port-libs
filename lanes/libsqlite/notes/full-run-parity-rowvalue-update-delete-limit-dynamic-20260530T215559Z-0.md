# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T215559Z-0

Status: ready for integration.

Accepted base: `e2fccb0f3569072f6fcb2b28f92689aa5a125f9e`.

Behavior fixed:
- `SQLiteRowValueNestedSavepointReturningPlan` no longer fatals when older direct row-array fixtures omit the row-id argument and use a scalar unique row-id column other than the current generic `setting_id` default.
- The plan keeps `setting_id` as the generic default for source-neutral callers and resolves a unique scalar `*_id` row column from normalized input rows only when the preferred row-id column is absent.
- The default inner savepoint name remains the generic existing observable direct-test value `app_inner_batch`.

Focused before evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php`
- Result before fix: fatal `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT row is missing rowid column setting_id`.

Focused after evidence:
- `php -l lanes/libsqlite/src/SQLiteRowValueNestedSavepointReturningPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php`
  - `1 test files, 76 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointRollbackBatchTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceTest.php`
  - `4 test files, 290 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-nested-savepoint-returning.php --self-test`
  - `OK`
- `git diff --check -- lanes/libsqlite`
  - passed

Expected dashboard movement:
- Restores 76 previously fatal focused PASS lines in `SQLiteRowValueNestedSavepointReturningTest.php`.
- `phpPass` expected movement: `844277 -> 844353`.
- Mapped denominator remains `1589 / 1589`; this is full-run parity repair, not new upstream mapping.

Dependency closure:
- No new support component is needed. The patch reuses native row-array UPDATE/DELETE LIMIT execution and row-value savepoint materialization.

Non-overlap:
- This does not add new corpus rows, metadata-only admission records, PDO behavior, WAL/VFS behavior, B-tree behavior, JSON behavior, or new domain-specific API. It fixes the named full-run row-value nested savepoint fatal on the current accepted base.
