# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T020444Z-0

Slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T020444Z-0`
Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

## Behavior

- Extended `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluation for predicate-valued scalar expressions.
- Added parity for comparison operators, `IS` / `IS NOT`, `BETWEEN` / `NOT BETWEEN`, `IN` / `NOT IN`, and unary `NOT` when those expressions are used as integer LIMIT/OFFSET values.
- Added row-value UPDATE/DELETE coverage where predicate-valued LIMIT/OFFSET expressions are applied both to the outer mutation window and to inner `IN (SELECT ... LIMIT/OFFSET ...)` tuple sources before row-value matching.
- NULL predicate results remain rejected as LIMIT/OFFSET datatype mismatches, matching the existing behavior for NULL scalar LIMIT expressions.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for scalar LIMIT/OFFSET expression and datatype mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for row-value tuple predicate behavior.

## Focused Growth

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from the accepted 2957 assertions / 990 PASS lines to 3634 assertions / 1187 PASS lines.
- Focused delta: `+677` assertions and `+197` TestRunner PASS lines.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 3634 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php | rg -c '^PASS '`
  - `1187`

## Dependency Closure

No new support component is needed. This reuses the native row-array UPDATE/DELETE RETURNING executor, row-value tuple source evaluator, shared ORDER BY/LIMIT selection plan, and scalar expression helpers.

## Non-Overlap

This extends the existing row-value/update-delete-limit dynamic parity file with predicate-valued LIMIT/OFFSET expressions only. It does not repeat accepted negative offset, arithmetic, quoted integral, unary plus, cast, exponent, hexadecimal, boolean literal, bitwise, CASE, scalar-function, ordinal, NULLS placement, grouped SELECT, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite evidence surfaces.
