# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T035239Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds row-value compound tuple sources using `UNION ALL`, with duplicate tuple
  preservation feeding SQLite `IN` membership semantics for UPDATE selection.
- Adds row-value compound tuple sources using `EXCEPT`, with ordered
  `LIMIT`/`OFFSET` applied to each arm before tuple subtraction feeds DELETE
  selection.
- Keeps generic `app_settings` / `app_setting_targets` fixtures only, with
  source-order RETURNING checks and selected-id checks.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value `IN (SELECT ...)` and compound tuple-source behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  ordered `LIMIT`/`OFFSET` windowing.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 6710 to
  6926 assertions.
- The added behavior contributes 72 focused TestRunner PASS cases and 216
  assertions over the current accepted row-value/update-delete-limit dynamic
  parity file.
- Mapped upstream denominator coverage is unchanged; this is already-mapped
  PHP behavior growth over row-value and LIMIT parity.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before: `1 test files, 6710 assertions, 0 failures`
  - after: `1 test files, 6926 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-source evaluator,
  compound tuple-source evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  with `UNION ALL` and `EXCEPT` tuple sources only. It does not repeat accepted
  DISTINCT duplicate tuple sources, `UNION` / `INTERSECT` tuple sources, cast,
  boolean, CASE, scalar function, scalar SELECT, predicate, Unicode length,
  concat, round, sign, app-WAL, JSON table, WAL/VFS, B-tree, PRAGMA,
  trigger/FK, source-neutral cleanup, or metadata-only suite evidence surfaces.
