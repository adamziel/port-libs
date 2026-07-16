# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T044219Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now accepts scalar `NOT LIKE` and
  `NOT GLOB` predicates in UPDATE/DELETE WHERE evaluation.
- The focused parity file exercises those predicates through row-value
  `IN (SELECT ...)` tuple subqueries, ordered dynamic LIMIT/OFFSET windows,
  UPDATE source-order RETURNING behavior, and DELETE result materialization.
- The added cases use only generic `app_settings` and `app_setting_targets`
  fixtures.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/like.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` for
  LIKE/GLOB and NOT predicate parity.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 8,201 to
  8,537 focused assertions.
- Added 96 focused TestRunner PASS cases for row-value subquery LIKE/GLOB and
  NOT LIKE/NOT GLOB LIMIT windows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 8537 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses existing native PHP
  UPDATE/DELETE RETURNING SQL parsing, row-value tuple subquery evaluation,
  LIMIT/OFFSET planning, and SQLite LIKE/GLOB helpers.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with scalar NOT LIKE/NOT GLOB predicates inside row-value subqueries. It does
  not repeat accepted negative offset, arithmetic/scalar LIMIT expressions,
  CAST/integral coercion, unicode length/codepoint LIMIT functions, row-value
  NULL tuple handling, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK,
  source-neutral cleanup, or metadata-only suite evidence surfaces.
