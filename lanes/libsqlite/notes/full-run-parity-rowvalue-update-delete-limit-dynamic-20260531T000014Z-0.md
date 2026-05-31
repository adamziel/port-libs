# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T000014Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts SQLite numeric LIMIT/OFFSET
  literals written as exponent values with signed exponents, such as `2e+0`,
  without mis-tokenizing the exponent sign as arithmetic.
- It also accepts hexadecimal integer LIMIT/OFFSET literals such as `0x3`,
  including row-value `IN (SELECT ...)` subquery windows.
- Non-integral exponent values such as `2.5e0` remain rejected as datatype
  mismatches.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET numeric-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 947 to 965
  assertions.
- The added behavior contributes 18 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 965 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with exponent and hexadecimal numeric LIMIT/OFFSET literal behavior only. It
  does not repeat the accepted negative offset, arithmetic, quoted integral,
  unary plus, cast result-type, computed ORDER BY length, grouped SELECT, JSON
  table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
