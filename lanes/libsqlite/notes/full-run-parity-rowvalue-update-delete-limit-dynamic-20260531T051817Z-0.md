# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T051817Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds row-value tuple `BETWEEN` predicate coverage for UPDATE selection
  windows.
- Adds row-value tuple `NOT BETWEEN` predicate coverage for DELETE
  comma-limit selection windows.
- Keeps selected-id order, RETURNING source order, mutation state, and
  deleted-row preservation checks over generic `app_settings` fixtures only.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` and
  `rowvalue4.test` for row-value comparison and tuple range behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET and comma-limit windowing semantics.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from `9969` to
  `10369` focused assertions in this worktree.
- Adds `80` focused TestRunner PASS cases to the existing dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before: `1 test files, 9969 assertions, 0 failures`
  - after: `1 test files, 10369 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple comparison evaluator,
  RETURNING expression evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  only for tuple `BETWEEN` and `NOT BETWEEN` selection windows. It does not
  repeat accepted scalar LIMIT expression, DISTINCT/compound tuple-source,
  row-value SELECT assignment, explicit tuple-list/VALUES RHS, JSON table,
  app-WAL, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
