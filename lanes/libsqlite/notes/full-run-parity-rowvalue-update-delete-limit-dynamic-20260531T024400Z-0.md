# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T024400Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts `round()` as a LIMIT/OFFSET
  scalar function when the evaluated result is losslessly integral.
- The parser supports one-argument and two-argument `round()` calls for outer
  UPDATE/DELETE RETURNING windows and row-value `IN (SELECT ...)` tuple source
  subquery windows.
- NULL, BLOB, nonnumeric text, malformed arity, and nonintegral precision
  results remain datatype mismatches for LIMIT/OFFSET evaluation.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression behavior and rounded numeric expression precedent.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 5032 to
  5517 assertions.
- The added behavior contributes 137 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 5517 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with `round()` LIMIT/OFFSET expression handling only. It does not repeat the
  accepted negative offset, arithmetic, quoted integral, unary plus,
  parenthesized unary negative, computed ORDER BY length, INTEGER/INT cast,
  REAL/NUMERIC/TEXT cast, boolean, CASE, coalesce/nullif/min/max, scalar
  SELECT, predicate, unicode length, concat-expression, grouped SELECT, JSON
  table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
