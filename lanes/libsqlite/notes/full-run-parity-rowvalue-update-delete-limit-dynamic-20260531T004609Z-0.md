# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T004609Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts integer-valued modulo,
  bitwise shift, bitwise AND/OR, unary bitwise-not, and `abs()` LIMIT/OFFSET
  expressions.
- The same LIMIT expression handling is used for outer UPDATE/DELETE mutation
  windows and for row-value `IN (SELECT ...)` tuple-source windows before
  tuple matching.
- Modulo by zero remains rejected as a LIMIT expression error.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  `e_update-3.3.*` and `e_update-3.4.*` for UPDATE LIMIT/OFFSET expression
  evaluation and ordered selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
  `e_delete-3.3.*` through `e_delete-3.7.*` for DELETE LIMIT/OFFSET
  expression evaluation, negative/no-limit handling, and ordered row
  selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 1314 to
  1339 assertions in this worktree.
- Focused PASS lines for that file are 472 after the change.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 1339 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the accepted row-value/update-delete-limit dynamic parity file
  with modulo, bitwise, and scalar `abs()` LIMIT expression parity only. It
  does not repeat the prior negative offset, arithmetic, quoted integral,
  unary plus, parenthesized unary negative, computed ORDER BY length, cast,
  exponent, hexadecimal, boolean, grouped SELECT, JSON table, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or suite-evidence
  surfaces.
