# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T061223Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now passes table context through row-value
  `BETWEEN` lower and upper tuple evaluation, so ordered scalar subqueries can
  define row-value range bounds inside UPDATE/DELETE predicates.
- Focused coverage exercises UPDATE and DELETE with row-value `BETWEEN` and
  `NOT BETWEEN` subquery bounds, outer `ORDER BY` / `LIMIT` windows,
  comma-form LIMIT, source-order RETURNING, and reversed-bound empty selection.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value `BETWEEN` semantics.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  row-value scalar subqueries with ordered `LIMIT` / `OFFSET`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 10775 to
  10795 assertions.
- The added behavior contributes 4 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 10795 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This owns row-value `BETWEEN` bounds sourced from ordered subqueries. It does
  not repeat prior scalar row-value comparison subqueries, row-value `IN`
  subquery LIMIT windows, LIMIT cast/expression coercion, JSON, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite
  evidence surfaces.
