# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T234308Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts integral `LIMIT` and `OFFSET`
  results from `CAST(... AS REAL)`, `CAST(... AS DOUBLE)`,
  `CAST(... AS NUMERIC)`, and `CAST(... AS TEXT)`, matching SQLite's rule that
  a LIMIT expression is valid when it evaluates losslessly to an integer.
- Nonintegral REAL/NUMERIC cast results and BLOB/NONE cast results remain
  rejected as datatype mismatches.
- Focused tests cover outer UPDATE/DELETE windows, comma-form LIMITs,
  row-value `IN (SELECT ...)` tuple sources, ordered tuple subqueries, and
  source-order RETURNING checks using only generic `app_settings` fixtures.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 646 to 947
  assertions.
- The added behavior contributes 86 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 947 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with non-INTEGER cast result types only. It does not repeat prior negative
  offset, arithmetic, quoted integral, unary plus, parenthesized unary
  negative, computed ORDER BY length, INTEGER/INT cast, grouped SELECT, JSON
  table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
