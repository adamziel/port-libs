# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T073140Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Added dynamic coverage for string/blob scalar LIMIT/OFFSET expressions in
  outer UPDATE windows and row-value DELETE tuple-source subqueries.
- Covered `printf()`, `format()`, `replace()`, `substr()`, `instr()`, `hex()`,
  `upper()`, `lower()`, `unicode()`, `octet_length()`, `zeroblob()`, and
  `randomblob()` where the scalar result is losslessly integral.
- Added malformed LIMIT/OFFSET checks for nonintegral formatted text, missing
  format arguments, NULL string-function results, invalid arity, empty
  `unicode()`, and nonintegral blob lengths.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test` for string,
  formatting, character, blob, and byte-length scalar semantics used by
  LIMIT/OFFSET expressions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` remain
  the UPDATE/DELETE ORDER BY LIMIT behavior source for this parity file.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 12548 to
  13092 assertions.
- The added behavior contributes 104 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before edit: `1 test files, 12548 assertions, 0 failures`
  - after edit: `1 test files, 13092 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator,
  scalar LIMIT/OFFSET evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with string/blob scalar LIMIT/OFFSET expressions only. It does not repeat the
  previous math scalar LIMIT/OFFSET slice, rowvalue4 NULL/empty scalar behavior,
  two-argument trim coverage, UPDATE/DELETE storage apply conflicts, JSON
  table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
