## real-upstream-corpus-trigger-fkey-dynamic-20260530T203347Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.

Ported a real upstream SQLite trigger/FK behavior cluster from
`test/fkey6.test`:

- `fkey6` evidence `R-18981-16292`: `PRAGMA defer_foreign_keys` delays
  foreign-key enforcement until the outermost commit.
- `fkey6` cases `3.3.1` through `3.3.4`: immediate `RESTRICT` blocks a parent
  delete before an AFTER DELETE trigger can repair it, while
  `defer_foreign_keys=1` lets the trigger reinsert the parent before commit.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair()`
  to model the immediate-vs-deferred RESTRICT boundary, trigger repair, missing
  parent commit failure, and transaction boundary dependencies using generic
  application setting rows.
- Added `SQLiteRealUpstreamTriggerFkeyDeferredRestrictDynamicTest.php` with
  70 dynamic row variants plus upstream-source citation assertions.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDeferredRestrictDynamicTest.php`
  passed: `1 test files, 2384 assertions, 0 failures`.

Non-overlap:

- This does not repeat the existing `fkey2` nocase trigger repair slice or the
  accepted `fkey7`/trigger action matrix rows. It targets `fkey6.test` deferred
  RESTRICT behavior and commit-boundary repair.

Dependency closure:

- No new support component is required. The slice reuses the existing bounded
  dynamic trigger/FK planner and adds one source-local behavior method.
