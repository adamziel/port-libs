# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T015927Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts constant scalar `SELECT`
  expressions in UPDATE/DELETE `LIMIT` and `OFFSET` positions.
- The same scalar-SELECT LIMIT evaluator is used for row-value
  `IN (SELECT ... LIMIT ... OFFSET ...)` tuple sources, so tuple matching sees
  the post-LIMIT source rows.
- Malformed scalar-SELECT LIMIT expressions that evaluate to NULL, BLOB,
  nonintegral numeric values, or require a FROM source are rejected.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 2693 to
  3005 assertions.
- The added behavior contributes 92 focused TestRunner PASS cases over the
  current accepted row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 3005 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with scalar-SELECT LIMIT/OFFSET expressions only. It does not repeat the
  accepted negative-offset, arithmetic, quoted integral, unary, cast, boolean,
  CASE, scalar-function, min/max, ordinal subquery, grouped SELECT, JSON table,
  WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
