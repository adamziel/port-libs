# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T010029Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates searched
  `CASE WHEN ... THEN ... ELSE ... END` expressions in UPDATE/DELETE
  `LIMIT` and `OFFSET` clauses.
- The same searched CASE LIMIT/OFFSET path is used inside row-value
  `IN (SELECT ... LIMIT ... OFFSET ...)` tuple sources before tuple matching.
- CASE branch selection uses SQLite-style truthiness for NULL, numeric, and
  text values.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 947 to
  1630 assertions.
- The added behavior contributes 91 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 1630 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with searched CASE LIMIT/OFFSET expressions only. It does not repeat prior
  negative offset, arithmetic, quoted integral, unary plus, cast, exponent,
  hexadecimal, boolean, bitwise, grouped SELECT, JSON table, WAL/VFS, B-tree,
  PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite evidence
  surfaces.
