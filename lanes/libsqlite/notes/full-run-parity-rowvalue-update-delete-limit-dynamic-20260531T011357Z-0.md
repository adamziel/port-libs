# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T011357Z-0

Slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T011357Z-0`
Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`

## Behavior

- Extended `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluation for scalar `coalesce()`, `ifnull()`, and `nullif()` calls.
- Added focused dynamic row-value UPDATE/DELETE LIMIT parity coverage for:
  - outer UPDATE/DELETE LIMIT windows using scalar functions;
  - row-value `IN (SELECT ... LIMIT/OFFSET ...)` tuple selection using scalar functions;
  - integral casts nested under scalar functions;
  - NULL-result and arity rejection for scalar LIMIT expressions.
- Upstream source truth remains real SQLite `limit.test`, `e_update.test`, and `e_delete.test`; this slice covers the scalar-expression continuation of the existing row-value UPDATE/DELETE LIMIT parity test.

## Test Delta

- Before edit in this worktree:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 1630 assertions, 0 failures`
- After edit:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 1903 assertions, 0 failures`
- Focused assertion growth: `+273`.
- Focused PASS-line growth: `+93`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`

## Dependency Closure

No new support component is needed. The slice reuses the native row-array UPDATE/DELETE RETURNING executor, row-value tuple predicates, and existing LIMIT/OFFSET planning.

## Non-Overlap

This does not repeat the accepted negative-offset, arithmetic, cast, boolean, bitwise, searched-CASE, or seeded dynamic LIMIT clusters. It adds scalar-function LIMIT expression parity only.
