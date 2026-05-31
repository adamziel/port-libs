# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T050035Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts row-value vector assignments
  sourced from a simple `SELECT ... FROM` table subquery.
- Correlated assignment subquery predicates can compare source columns against
  the target row, apply `ORDER BY`, and apply `LIMIT` / comma-form `LIMIT`.
- Empty assignment subqueries assign SQL NULL values, matching upstream
  `rowvalue7.test`.
- Focused dynamic tests combine that row-value assignment behavior with outer
  UPDATE limit windows and row-value `IN (SELECT ...)` tuple-source windows.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue7.test` for
  row-value assignment from SELECT and empty subquery NULL assignment behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` now passes with
  `9408` assertions.
- Adds 53 focused TestRunner PASS cases over the current accepted
  row-value/update-delete-limit dynamic parity file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 9408 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING parser, row-value predicate evaluator, row-array
  ORDER BY/LIMIT selection, and generic table fixtures.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with row-value assignment `SELECT ... FROM` support from upstream
  `rowvalue7.test`. It does not repeat prior negative offset, scalar LIMIT
  expression, cast, boolean, CASE, min/max, scalar SELECT LIMIT, unicode
  length, row-value tuple-source, JSON table, WAL/VFS, B-tree, PRAGMA,
  trigger/FK, source-neutral cleanup, or metadata-only suite evidence
  surfaces.
