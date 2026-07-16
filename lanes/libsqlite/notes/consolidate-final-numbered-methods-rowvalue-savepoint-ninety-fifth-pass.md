# Rowvalue Savepoint Consolidation Ninety-Fifth Pass

## Scope

- Consolidated duplicate row-count helper bodies in `SQLiteRowValueUpdateDeleteReturningSavepointPlan`.
- Kept existing observable status strings, dependency labels, result keys, savepoint names, and `nextNN` proof text unchanged.
- No production numbered class/file/helper method was introduced.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext163Test.php`
  - `2 test files, 123 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValue*Savepoint*.php`
  - `86 test files, 5518 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency Closure

No new support component is needed. This is production-helper consolidation only and reuses the existing row-value UPDATE/DELETE RETURNING savepoint executor.
