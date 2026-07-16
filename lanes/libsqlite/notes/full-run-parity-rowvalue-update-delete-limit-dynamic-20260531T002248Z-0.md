# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T002248Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now treats `TRUE` and `FALSE` as integer
  SQL literals when evaluating UPDATE/DELETE `LIMIT` and `OFFSET` expressions.
- Boolean literal arithmetic such as `TRUE+TRUE` now works in both outer
  UPDATE/DELETE windows and row-value `IN (SELECT ... LIMIT/OFFSET ...)`
  tuple sources.
- `LIMIT FALSE` produces an empty mutation window while preserving the input
  table image.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET window behavior and datatype mismatch handling.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior, including boolean tuple expressions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/istrue.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` for
  SQLite `TRUE`/`FALSE` expression literal semantics.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 947 to
  1314 assertions.
- Focused PASS lines grow from the accepted 320 to 447, for +127 focused
  TestRunner PASS cases in this file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 1314 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php | rg -c '^PASS '`
  - `447`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with boolean SQL literal LIMIT/OFFSET expression behavior only. It does not
  repeat prior negative offset, arithmetic, quoted integral, unary plus,
  parenthesized unary negative, computed ORDER BY length, numeric cast,
  hexadecimal/exponent literal, grouped SELECT, JSON table, WAL/VFS, B-tree,
  PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite evidence
  surfaces.
