# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T025748Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates SQLite `sign()` in
  UPDATE/DELETE `LIMIT` and `OFFSET` expressions when the result is a lossless
  integer.
- Focused dynamic cases cover outer UPDATE windows and row-value DELETE
  `IN (SELECT ...)` subquery windows where `sign()` participates in the
  selected LIMIT/OFFSET values.
- Malformed `sign(NULL)`, nonnumeric text, BLOB, and bad-arity LIMIT/OFFSET
  expressions remain rejected as datatype or syntax mismatches.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression datatype behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test` for core
  scalar expression semantics around numeric scalar functions.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grew from 5,517 to
  5,857 focused assertions in this worktree.
- Added 100 focused TestRunner PASS cases to the existing row-value/update-
  delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before: `1 test files, 5517 assertions, 0 failures`
  - after: `1 test files, 5857 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  only for `sign()` LIMIT/OFFSET expressions. It does not repeat prior
  negative offset, arithmetic, cast, scalar-select, predicate, Unicode length,
  concat, round, grouped SELECT, JSON table, WAL/VFS, B-tree, PRAGMA,
  trigger/FK, source-neutral cleanup, or metadata-only suite evidence surfaces.
