# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T055029Z-0`

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
LIMIT parity.

## Behavior

- `SQLiteUpdateDeleteReturningSql` now evaluates parenthesized row-value
  scalar `SELECT` subqueries on the RHS of row-value comparison predicates.
- The implementation reuses the existing row-value simple SELECT tuple path,
  preserving `ORDER BY`, `LIMIT`, `OFFSET`, comma-form LIMIT, and arity
  checks.
- Focused tests cover UPDATE and DELETE `=`, `<>`, `>`, and `<=` predicates
  with ordered scalar row-value subqueries plus RETURNING expression
  evaluation.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
for row-value scalar subquery comparison behavior, alongside existing
`e_update.test` and `e_delete.test` UPDATE/DELETE LIMIT coverage.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - PASS: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - PASS: `1 test files, 10432 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
  - PASS: `2 test files, 10534 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - PASS.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: guard file is absent in this worktree.

## Non-overlap

This does not repeat the accepted row-value `IN (SELECT ...)`, tuple-list,
LIMIT expression, math scalar, RETURNING savepoint/window, or UPDATE/DELETE
ORDER BY LIMIT selection surfaces. The new surface is specifically row-value
comparison against a scalar row-value subquery in UPDATE/DELETE predicates and
RETURNING expressions.

## Dependency Closure

No new support component is needed. This extends existing native PHP row-value
subquery evaluation inside the current UPDATE/DELETE RETURNING executor.
