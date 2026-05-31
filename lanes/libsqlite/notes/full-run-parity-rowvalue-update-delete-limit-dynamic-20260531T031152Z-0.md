# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T031152Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds row-value `IN (SELECT DISTINCT ...)` tuple sources with duplicate input
  rows, ordered `LIMIT`/`OFFSET`, and generic UPDATE mutation checks.
- Adds row-value compound tuple sources using `UNION` and `INTERSECT`, with
  per-arm `ORDER BY` plus `LIMIT`/`OFFSET`, feeding DELETE and UPDATE
  selection windows.
- Keeps source-order RETURNING checks and selected-id checks over generic
  `app_settings` fixtures only.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value `IN (SELECT ...)`, compound tuple-source, and duplicate tuple
  behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  ordered `LIMIT`/`OFFSET` windowing.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` adds 96 focused
  assertions across 96 additional TestRunner PASS cases.
- Mapped upstream denominator coverage is unchanged; this is already-mapped
  PHP behavior growth over row-value and LIMIT parity.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 6145 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator,
  compound tuple-source evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with DISTINCT duplicate tuple sources and compound `UNION`/`INTERSECT` tuple
  sources only. It does not repeat accepted cast, boolean, CASE, scalar
  function, scalar SELECT, predicate, Unicode length, concat, round, sign,
  app-WAL, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral
  cleanup, or metadata-only suite evidence surfaces.
