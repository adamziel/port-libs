# real-upstream-corpus-trigger-fkey-dynamic-20260531T000217Z-0

Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/temptrigger.test`
- Ported sections:
  - `temptrigger-1.1..1.5`: shared-cache schema reload must not make the owner connection's temp trigger disappear.
  - `temptrigger-2.1..2.5`: opening/closing a peer shared-cache connection must not remove the owner temp trigger.
  - `temptrigger-3.1..3.4`: attached database schema reload must preserve temp triggers on attached tables.
  - `temptrigger-6.0..7.6`: temp trigger name resolution across main and attached schemas, including persistent-trigger attached-reference rejection.
  - `temptrigger-8.1.1..8.3.3`: qualified INSERT/UPDATE/DELETE targets are rejected in persistent trigger bodies but allowed in TEMP trigger bodies.
  - `temptrigger-9.0..9.5.3`: chained TEMP triggers across attached database schemas route insert/update/delete effects.

## Implementation

- Added temp-trigger planner behavior to `SQLiteDynamicTriggerForeignKeyPlan`:
  - `tempTriggerSharedCacheReloadPlan()`
  - `tempTriggerQualifiedBodyPlan()`
  - `tempTriggerNameResolutionPlan()`
  - `tempTriggerAttachedChainPlan()`
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerCorpusTest.php` with 14,568 focused TestRunner PASS cases and 14,575 assertions.

## Non-Overlap

This does not repeat accepted `fkey1`/`fkey2`/`fkey3`/`fkey4`/`fkey5`/`fkey6`/`fkey7`/`fkey8`, `e_fkey`, trigger1 lifecycle and statement-preservation, trigger2 row order, trigger3 RAISE, trigger4 view routing, trigger5 undo, trigger7/8/B/C/D/E/F/G, trigger9 old-row materialization, or PRAGMA foreign-key check/catalog batches. It owns previously uncovered `temptrigger.test` connection-local TEMP trigger behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTempTriggerCorpusTest.php`
  - `1 test files, 14575 assertions, 0 failures`
  - PASS lines counted from output: `14568`

## Dependency Closure

No new support component is needed. This slice reuses the existing generic trigger/FK planner surface and the hydrated upstream SQLite corpus as source truth.
