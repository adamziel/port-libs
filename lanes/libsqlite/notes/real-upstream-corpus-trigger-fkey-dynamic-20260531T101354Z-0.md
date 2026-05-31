# real-upstream-corpus-trigger-fkey-dynamic-20260531T101354Z-0

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

Implemented a source-neutral real upstream trigger/FK dynamic batch for the
exact statement conflict-policy cases in `fkey2.test`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported sections: `fkey2-20.2.1..20.2.4` and `fkey2-20.3.1..20.3.10`

## Behavior Added

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2ConflictPolicyForeignKeyPlan()`.
- Models immediate foreign-key failures for `INSERT`, `INSERT OR IGNORE`,
  `INSERT OR ABORT`, `INSERT OR ROLLBACK`, `INSERT OR REPLACE`, and
  `INSERT OR FAIL` without allowing the conflict policy to override the FK
  constraint error.
- Models `UPDATE OR ...` failures for both parent-key and child-key changes.
- Preserves prior transaction writes after a failed FK statement and verifies
  the failed statement rolls back without rolling back the whole transaction.
- Reports the statement phase, unchanged parent/child row images, violation
  rows, upstream case ids, and dependency labels for each dynamic case.

## Focused Evidence

Red-first check before the production helper existed:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictPolicy20260531Test.php`
  - `1 test files, 15 assertions, 1005 failures`

Passing checks after the implementation:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictPolicy20260531Test.php`
  - `1 test files, 22059 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictPolicy20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicImplicitDrop20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `5 test files, 103352 assertions, 0 failures`

Focused TestRunner PASS cases: `+1008` from two upstream-source citation
tests, 1002 dynamic statement-policy variants, two invalid-input guards, one
ownership/count test, and one non-overlap/dependency-closure test.

## Non-Overlap

This expands the exact upstream `fkey2-20.2.*` and `fkey2-20.3.*`
`INSERT/UPDATE OR ...` statement conflict-policy cases. It is distinct from
the older accepted `fkey2-20` action-matrix summary in
`SQLiteRealUpstreamTriggerFkeyDynamicTest.php`, which exercises FK action
helpers and one generic FK failure guard but does not model the exact
statement conflict policies, insert-vs-update case ids, or transaction
preservation outcomes from this upstream block.

This also avoids accepted `fkey2-3` statement rollback, `fkey2-17`
`count_changes`, `fkey8` action journals, implicit DROP, trigger2 outer
conflict propagation, triggerF WITHOUT ROWID conflict-delete, JSON, VFS, WAL,
B-tree, and RETURNING surfaces.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native
trigger/FK dynamic planner and hydrated SQLite upstream checkout. Mapped
coverage remains `1589 / 1589`; this is PASS-line and assertion growth over
already mapped upstream `fkey2.test` inventory.
