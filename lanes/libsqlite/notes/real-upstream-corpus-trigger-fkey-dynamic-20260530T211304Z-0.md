# real-upstream-corpus-trigger-fkey-dynamic-20260530T211304Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger6.test`
- Ported sections: `trigger6-1.1` through `trigger6-1.6`.
- Scenario: side-effecting INSERT/UPDATE expressions are evaluated once, and the same evaluated value is visible through `NEW.*` in BEFORE row triggers.

Focused PHP coverage:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation()` as a generic native model for the trigger6 expression-evaluation boundary.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicRealTriggerTest.php` with 1,004 focused TestRunner PASS cases and 1,006 assertions.
- The 1,000 dynamic corpus cases cover INSERT, INSERT with counter arguments plus offset, UPDATE, and UPDATE with counter arguments plus offset across 250 generated row/counter variants.

Non-overlap:

- Does not repeat accepted fkey1 replacement cascade, fkey2/fkey6 deferred restrict and pragma-toggle batches, fkey5 foreign-key-check corpus, fkey8 action-journal behavior, trigger2 view behavior, trigger3 RAISE behavior, trigger5 undo behavior, recursive trigger batches, UPSERT/RETURNING trigger coverage, or PRAGMA FK-list/index-xinfo families.
- This slice owns the previously uncovered upstream `trigger6.test` expression value reuse surface.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRealTriggerTest.php`
  - `1 test files, 1006 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRealTriggerTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing lane-local dynamic trigger/FK planner surface and cites the hydrated upstream SQLite Tcl source directly.
