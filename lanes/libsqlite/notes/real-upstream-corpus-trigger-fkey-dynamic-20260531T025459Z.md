# Real Upstream Trigger/FK Dynamic Corpus

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T025156Z-0`

Base accepted HEAD: `892244279ab2272eec684ce3477ab002d81ab0b4`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test`
  - `fkey7-1.2` through `fkey7-1.5`: authorizer-observed FK table read dependencies for parent-key updates.
  - `fkey7-2.1` and `fkey7-2.2`: literal and bound `zeroblob()` child inserts fail FK checks and leave child rows empty.
  - `fkey7-3.0`: STAT4/ANALYZE does not disturb deferred FK-valid rows.
  - `fkey7-4.1` through `fkey7-4.6`: `INSERT OR FAIL` reports FK failure before a parent exists, then UNIQUE failure after the parent exists, with clean `foreign_key_check`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-1.1.1` through `trigger2-1.1.7`: BEFORE/AFTER row trigger timing across rowid, integer-primary-key, declared-primary-key, indexed, and temp table definitions.

Focused movement:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php` reported `570 assertions, 0 failures`.
- After: same focused command reports `1219 assertions, 0 failures`.
- New focused assertion delta: `+649`.

Non-overlap:

- This extends the existing real upstream `fkey2.test` deferred-savepoint corpus with `fkey7.test` read-dependency/OR-FAIL/zeroblob/STAT4 behavior and `trigger2.test` row-timing behavior.
- It does not add generated fake upstream script IDs, dashboard-only rows, domain-specific APIs, or compatibility wrappers.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local PHP TestRunner execution and adds a bounded generic trigger/FK plan under `lanes/libsqlite/src`.
