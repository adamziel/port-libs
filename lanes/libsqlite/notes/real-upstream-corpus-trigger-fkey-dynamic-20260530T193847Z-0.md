# real-upstream-corpus-trigger-fkey-dynamic-20260530T193847Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T193847Z-0`
- Base accepted HEAD: `bc1638b6eb86853297e97bc15107a4f4f8e9ef19`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test`
- Scenario ranges:
  - `fkey7-1.2` through `fkey7-1.5`: parent UPDATE authorizer read-set evidence for FK parent and child probes.
  - `fkey7-4.1` through `fkey7-4.6`: `INSERT OR FAIL` ordering for FK failure before UNIQUE failure and preservation of prior successful rows on UNIQUE failure.

## Focused Delta

Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyUpdateReadSet()` and
`SQLiteDynamicTriggerForeignKeyPlan::foreignKeyOrFailInsert()` with a focused
real-upstream corpus test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7CorpusTest.php`
- Focused result: `1 test files, 9124 assertions, 0 failures`

This is non-overlapping with the accepted `fkey1`, `fkey2`, `fkey3`, `fkey4`,
`fkey6`, `e_fkey`, `trigger1`, `trigger2`, `trigger3`, and `triggerC` trigger/FK
dynamic batches. The new surface is specifically upstream `fkey7.test` FK
authorizer read dependencies and `INSERT OR FAIL` FK/UNIQUE ordering.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7CorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7CorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7CorpusTest.php`
  - `1 test files, 9124 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds bounded native PHP models for FK dependency reads
and `OR FAIL` row-application ordering.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range, preferably
later `fkey7.test` STAT4/blob edge cases or `fkey8.test`, only if it can maintain
the real-corpus handoff floor.
