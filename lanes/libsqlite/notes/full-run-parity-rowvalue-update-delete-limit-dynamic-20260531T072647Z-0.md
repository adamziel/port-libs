# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T072647Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now models SQLite `printf()` / `format()`
  `%c` precision semantics for LIMIT/OFFSET expressions: `%.*c` and `%.Nc`
  repeat the formatted character instead of using PHP `sprintf()`'s single-byte
  character behavior.
- Added dynamic UPDATE windows and row-value DELETE tuple-source subqueries
  whose LIMIT/OFFSET expressions depend on repeated `%c` lengths.
- Added malformed precision coverage for missing and nonintegral dynamic
  precision arguments.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
  `func-9.14`, which uses `printf('abc%.*cxyz',x,'m')` and replacement length
  checks.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from the prior
  accepted note baseline of `12548` assertions to `12988` assertions.
- The added behavior contributes 101 focused TestRunner PASS cases in the
  existing current-base row-value/update-delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - after edit: `1 test files, 12988 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This extends the existing native PHP
  scalar LIMIT/OFFSET evaluator used by the UPDATE/DELETE RETURNING executor.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with SQLite repeated `%c` `printf()` / `format()` LIMIT expression behavior
  only. It does not repeat accepted quote/typeof, zeroblob/randomblob,
  arithmetic/cast/CASE/coalesce/nullif, length/unicode/concat/round/sign,
  math scalar, rowvalue4 NULL/empty scalar, two-argument trim, JSON, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite
  evidence surfaces.
