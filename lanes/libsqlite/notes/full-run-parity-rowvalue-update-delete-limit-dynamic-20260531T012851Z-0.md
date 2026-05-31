# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T012851Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates scalar `min()` and `max()`
  LIMIT/OFFSET expressions when the result is losslessly integral.
- The same expression support applies to outer UPDATE/DELETE RETURNING LIMIT
  windows and row-value `IN (SELECT ...)` tuple-source LIMIT/OFFSET windows.
- NULL, BLOB, and nonintegral scalar `min()`/`max()` LIMIT/OFFSET results
  remain rejected as datatype mismatches.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  `e_update-3.*` for UPDATE ORDER BY/LIMIT expression selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
  `e_delete-3.*` for DELETE ORDER BY/LIMIT expression selection and datatype
  mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 1903 to
  2187 assertions in this accepted-base worktree.
- The added behavior contributes 84 focused TestRunner PASS cases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 2187 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with `min()`/`max()` scalar LIMIT/OFFSET expressions only. It does not repeat
  prior negative offset, arithmetic, quoted integral, unary plus,
  parenthesized unary negative, computed ORDER BY length, CAST, exponent,
  hexadecimal, boolean, bitwise, CASE, coalesce/ifnull/nullif, grouped SELECT,
  JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
