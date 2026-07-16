# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T013535Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds simple `CASE <expr> WHEN <expr> THEN ... ELSE ... END` LIMIT/OFFSET
  windows over UPDATE RETURNING rows.
- Adds row-value `IN (SELECT ... LIMIT/OFFSET ...)` DELETE RETURNING coverage
  where the inner tuple source uses simple CASE expressions before matching.
- Keeps RETURNING rows in source mutation order while the selected row ids are
  chosen by the ORDER BY/LIMIT window.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 2129 to
  2409 assertions.
- The added behavior contributes 80 focused TestRunner PASS cases.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 2409 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, simple
  CASE expression evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with simple CASE LIMIT/OFFSET windows only. It avoids accepted negative
  offset, arithmetic, quoted integral, unary, cast, searched CASE, coalesce,
  ordinal subquery, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, and metadata-only suite evidence surfaces.
