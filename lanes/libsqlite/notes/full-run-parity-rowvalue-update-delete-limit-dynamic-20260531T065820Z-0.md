# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T065820Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Added dynamic coverage for math scalar LIMIT/OFFSET expressions in outer
  UPDATE windows and row-value DELETE tuple-source subqueries.
- Covered `ceil()`, `floor()`, `trunc()`, `sqrt()`, `pow()`, and `power()`
  where the scalar result is losslessly integral, plus malformed NULL, BLOB,
  nonintegral, negative-square-root, and arity cases.
- The cases preserve SQLite UPDATE/DELETE ORDER BY LIMIT selection behavior,
  row-value tuple source filtering, source-order RETURNING rows, and generic
  `app_settings` fixture names.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test` for scalar
  math function semantics used by LIMIT/OFFSET expressions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  row-value scalar tuple behavior in the surrounding dynamic parity file.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 12007 to
  12548 assertions.
- The added behavior contributes 103 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before edit: `1 test files, 12007 assertions, 0 failures`
  - after edit: `1 test files, 12548 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator,
  scalar LIMIT/OFFSET evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with math scalar LIMIT/OFFSET expressions only. It does not repeat prior
  negative offset, arithmetic, casts, booleans, CASE, coalesce/nullif, ordinal
  tuple sources, NULLS placement, length/unicode/concat/round/sign, rowvalue4
  NULL/empty scalar behavior, two-argument trim coverage, JSON table, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite
  evidence surfaces.
