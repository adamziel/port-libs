# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T063642Z-0`

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

## Behavior

- `SQLiteUpdateDeleteReturningSql` now evaluates SQLite `zeroblob()` constant
  expressions in UPDATE/DELETE `LIMIT` and `OFFSET` clauses.
- The dynamic row-value matrix covers `length(zeroblob(...))`,
  `octet_length(zeroblob(...))`, `length(hex(zeroblob(...)))/2`, negative
  zeroblob lengths, non-integral length rejection, and arity rejection.
- The new behavior is exercised through both outer UPDATE windows and
  row-value `IN (SELECT ...)` DELETE tuple windows using generic
  `app_settings` fixtures.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test` for
  SQLite `zeroblob`, `length`, `octet_length`, and `hex` scalar behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete.test` remain
  the UPDATE/DELETE LIMIT behavior anchors for the matrix file.

## Focused Growth

- `SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php` grows from 219
  assertions to 417 assertions, for +198 focused assertions in this slice.
- Combined row-value/update-delete-limit focused coverage passes at 2 files /
  11887 assertions / 0 failures.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was not run because that guard file is not present in this checkout.
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This extends the existing native PHP
constant-expression evaluator used by the row-value UPDATE/DELETE LIMIT
executor.

## Non-overlap

This slice does not repeat prior negative offset, comma LIMIT, cast, exponent,
hex literal, boolean, bitwise, CASE, coalesce, printf/format, scalar row-value
subquery, BETWEEN-subquery, or quote/typeof LIMIT behavior. It only adds the
missing `zeroblob()` scalar expression path and its row-value
UPDATE/DELETE LIMIT matrix coverage.
