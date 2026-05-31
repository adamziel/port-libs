# real-upstream-corpus-trigger-fkey-dynamic-20260531T071550Z-0

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/temptrigger.test`
- Ported sections:
  - `temptrigger-4.0..4.1`: creating a temp table with the same name after a temp trigger on the main table must not rebind or corrupt the temp trigger.
  - `temptrigger-5.0..5.2`: if a peer connection drops the main target table, the owner connection can still query schema state and the connection-local temp trigger row remains in `temp.sqlite_master`.

## Implementation

- Added `SQLiteDynamicTriggerForeignKeyPlan::tempTriggerTargetLifecyclePlan()` for the missing temp-trigger lifecycle cases.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerLifecycleTest.php` with 170 dynamic seeds across both upstream scenarios.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerLifecycleTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerLifecycleTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerLifecycleTest.php`
  - `1 test files, 7315 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerLifecycleTest.php`
  - `2 test files, 21890 assertions, 0 failures`

Expected selected PASS/assertion movement: `+7315` focused behavior assertions from real upstream `temptrigger.test`.

## Non-Overlap

This does not repeat accepted temptrigger shared-cache reload, attached-schema reload, name-resolution, qualified body DML, or attached chain behavior. It specifically covers the previously separate `temptrigger-4.*` and `temptrigger-5.*` lifecycle cases. It also avoids fkey action/defer/check families, triggerupfrom, triggerC, triggerG, e_droptrigger, PRAGMA schema invalidation, WAL, VFS, B-tree, JSON, SELECT, and source-neutral cleanup batches.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local dynamic trigger/FK plan helper and adds a bounded temp-trigger lifecycle branch.
