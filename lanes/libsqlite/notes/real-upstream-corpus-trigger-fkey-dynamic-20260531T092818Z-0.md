# real-upstream-corpus-trigger-fkey-dynamic-20260531T092818Z-0

Implemented a source-neutral real upstream trigger/FK dynamic batch for
`fkey2.test` `fkey2-genfkey.1.1..3.6`, the upstream block that checks built-in
foreign-key behavior against the old generated-trigger compatibility cases.

## Changed Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T092818Z-0.md`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Sections: `fkey2-genfkey.1.1..1.19`, `fkey2-genfkey.2.1..2.6`,
  `fkey2-genfkey.3.1..3.6`

## Behavior Added

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2GenfkeyCompatibilityPlan()`
  with native PHP modeling for:
  - NO ACTION single-column and composite FK rejection/rollback shape.
  - CASCADE update/delete propagation into single-column and composite child
    keys.
  - SET NULL update/delete propagation into single-column and composite child
    keys.
  - Composite child NULL short-circuit behavior.
  - Integer-primary-key parent update to NULL datatype mismatch.
  - Composite parent lookup using the declared FK parent key order even when
    the upstream schema uses a unique index in the opposite declaration order.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php`
  - `1 test files, 55008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Focused TestRunner PASS cases: `+1003` from one upstream-source citation test,
1000 dynamic variants, one invalid-input guard, and one ownership/count test.
Focused behavior assertions: `55008`.

## Non-overlap

This does not repeat the accepted `e_fkey-52`/`e_fkey-53` parent-key
distinctness batch, fkey2 authorizer/reset, fkey2 count_changes, fkey2
set-default/nocase/deferred-graph/self-reference/conflict-policy batches,
fkey5 check rows, fkey6 defer-pragma/restrict repair, fkey7/fkey8, trigger
program, temp-trigger, rowid-variable, or RETURNING/UPSERT trigger batches.
The new surface is specifically the upstream `fkey2-genfkey.*` compatibility
block.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native
trigger/FK planner and hydrated upstream SQLite source cache. Mapped coverage
stays `1589 / 1589`; this is PASS-line and assertion growth over already mapped
upstream `fkey2.test` inventory.
