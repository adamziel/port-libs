# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T014008Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now parses `ORDER BY ... NULLS FIRST|LAST`
  for UPDATE/DELETE RETURNING selection windows.
- `SQLiteUpdateDeleteLimitPlan` preserves NULL placement through the shared
  `SQLiteSelectResult::orderBy()` sorter and exposes it in plan summaries.
- Row-value `IN (SELECT ...)` subquery ordering now honors explicit NULL
  placement before LIMIT/OFFSET decides which tuples qualify the mutation.
- `nullif()` is available to row-value subquery ORDER BY expressions, matching
  the existing LIMIT-expression scalar support used by this parity file.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  ordered LIMIT/OFFSET selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 738 to 826
  focused TestRunner PASS cases.
- Assertion count grows from 2129 to 2677, a +548 assertion delta.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteLimitPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 2677 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple subquery evaluator,
  shared SELECT sorter, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with explicit NULL placement ordering for mutation windows and row-value
  subqueries. It does not repeat prior negative offset, scalar function, cast,
  boolean, CASE, arithmetic, ordinal, min/max LIMIT/OFFSET, grouped SELECT,
  JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
