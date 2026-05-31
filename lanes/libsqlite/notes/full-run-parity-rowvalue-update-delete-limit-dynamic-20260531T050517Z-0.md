# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T050517Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Adds explicit row-value tuple-list RHS coverage for UPDATE selection windows.
- Adds `VALUES (...)` row-value RHS coverage for DELETE selection windows.
- Keeps selected-id, RETURNING source-order, mutation, and malformed tuple RHS
  checks over generic `app_settings` fixtures only.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` and
  `rowvalue4.test` for row-value `IN (...)`, explicit tuple RHS, `VALUES`
  tuple RHS, NULL/arity rejection, and tuple comparison behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET windowing semantics around the selected row set.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grew from 9,137 to
  9,500 focused assertions in this worktree.
- Adds 75 focused TestRunner PASS cases to the existing dynamic parity file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - before: `1 test files, 9137 assertions, 0 failures`
  - after: `1 test files, 9500 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple-list evaluator, `VALUES`
  tuple-list evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file
  only for explicit tuple-list and `VALUES` RHS row-value selection. It does
  not repeat accepted scalar LIMIT functions, DISTINCT/compound tuple sources,
  row-value SELECT assignment, JSON table, app-WAL, WAL/VFS, B-tree, PRAGMA,
  trigger/FK, source-neutral cleanup, or metadata-only suite evidence surfaces.
