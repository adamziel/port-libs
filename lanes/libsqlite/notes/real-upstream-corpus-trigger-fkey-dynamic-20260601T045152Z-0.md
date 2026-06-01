# real-upstream-corpus-trigger-fkey-dynamic-20260601T045152Z-0

Base accepted HEAD: `5a7dc1daad24ba95a3c58d82c78018bfc7722899`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections: `e_fkey-4.1` through `e_fkey-14.4`.

## Behavior

- Foreign-key enforcement is disabled by default until `PRAGMA foreign_keys=ON`.
- `PRAGMA foreign_keys` reports connection-local state, and attempts to toggle it inside a transaction are no-ops.
- Parent updates cascade only when enforcement is enabled and the schema action is `ON UPDATE CASCADE`.
- Missing-parent child inserts, dependent-parent deletes, and dependent-parent key updates roll back the statement.
- NULL child keys satisfy the FK relationship unless the child column is separately `NOT NULL`.
- The upstream `trackartist IS NULL OR EXISTS(parent)` invariant stays true across dynamic accepted and rejected statements.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRuntimeIntro20260601Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260601T045152Z-0.md`

## Focused Evidence

- Red-first:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRuntimeIntro20260601Test.php`
  - Failed before the helper existed with `Call to undefined method PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan::foreignKeyRuntimeIntroPlan()`.
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRuntimeIntro20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRuntimeIntro20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRuntimeIntro20260601Test.php`
  - `1 test files, 10260 assertions, 0 failures`

## Countability

- Focused selected movement: `+10260` behavior assertions from real upstream `e_fkey.test` runtime-introduction cases.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth over an already mapped upstream file, not new denominator mapping.

## Non-Overlap

This does not repeat accepted `e_fkey-1..3` capability mode, `e_fkey-15..17` parent comparison/affinity, `e_fkey-18..24` required-index diagnostics, `e_fkey-25..27` child lookup planning, `e_fkey-31..38` savepoint boundaries, `e_fkey-39..53` FK action matrices, `e_fkey-54` create-table definition validation, `e_fkey-57..64` implicit DROP/MATCH/recursive-action behavior, `fkey2` graph/counter/self-reference/DDL batches, `fkey5` checks, triggerA view-WHERE routing, triggerB wide masks, triggerC recursion, triggerG recursive SELECT, RETURNING/upsert, WAL, VFS, B-tree, PRAGMA schema, or source-neutral cleanup batches.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP trigger/FK dynamic planner surface and the hydrated SQLite upstream checkout as source truth.

