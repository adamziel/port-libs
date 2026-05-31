# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T060828Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
LIMIT parity.

Behavior:

- `SQLiteUpdateDeleteReturningSql` now preserves scalar row-value subquery
  arity when a `SELECT` RHS returns zero rows. The empty scalar subquery is
  treated as a NULL tuple with the SELECT column count, matching SQLite
  row-value comparison behavior instead of failing with an arity mismatch.
- Focused tests cover `=`, `<>`/`!=`, `IS`, `IS NOT`, and
  `IS DISTINCT FROM` against empty row-value scalar subqueries in dynamic
  UPDATE and DELETE windows.

Upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  row-value scalar subquery comparison behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`,
  `e_update.test`, and `e_delete.test` remain the LIMIT/OFFSET and
  UPDATE/DELETE source context for the focused file.

Focused assertion growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grew from `10775` to
  `11114` focused assertions, for `+339` assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  passed with `1 test files, 11114 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses the
existing UPDATE/DELETE RETURNING SQL parser, row-value scalar subquery
evaluator, NULL row-value comparison logic, and LIMIT/OFFSET selection plan.

Non-overlap: this extends the current row-value/update-delete-limit dynamic
parity file only for empty scalar row-value subquery RHS handling. It does not
repeat accepted VALUES tuple lists, scalar LIMIT expression families, SELECT
SQL text/JOIN/GROUP/subquery clusters, JSON table, WAL/VFS, B-tree, planner,
PRAGMA, trigger, suite evidence, or source-neutral cleanup work.
