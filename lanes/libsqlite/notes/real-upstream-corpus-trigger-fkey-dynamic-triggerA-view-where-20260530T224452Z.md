# real-upstream-corpus-trigger-fkey-dynamic-triggerA-view-where-20260530T224452Z

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T224452Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerA.test`
- Ported range: `triggerA-2.1` through `triggerA-2.11`

Implemented behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewWhereRoutingPlan()` to model upstream INSTEAD OF DELETE/UPDATE triggers on materialized views.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTriggerAViewWhereTest.php` with 100 deterministic seeds across `v1`, `v2`, `v3`, `v4`, and `v5`, for both DELETE and UPDATE trigger events.
- The test asserts the outer statement WHERE is applied before the view trigger receives OLD/NEW rows, including simple table views, filtered views, compound UNION views, and joined views.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerAViewWhereTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerAViewWhereTest.php`
- Result: `1 test files, 14005 assertions, 0 failures`

Expected movement:

- `phpPass`: `991889 -> 1005894` from 14,005 newly passing focused TestRunner assertions.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior growth over already mapped real upstream trigger inventory.

Non-overlap:

- This does not repeat accepted trigger/FK fkey2, fkey3, fkey4, fkey5, fkey6, fkey7, fkey8, e_fkey, trigger1, trigger2, trigger3 RAISE, trigger4 view routing, trigger5 undo, trigger7, trigger8 large-body, triggerB wide-column/recursive queue, triggerC rowid mutation, triggerD rowid alias, triggerE variable rejection, triggerF WITHOUT ROWID, triggerG recursive, or PRAGMA foreign-key check/catalog batches.
- This slice owns the real upstream `triggerA.test` INSTEAD OF view trigger WHERE propagation matrix.

Dependency closure:

- No new support component is needed. The implementation reuses lane-local row-array trigger/view materialization and adds the bounded triggerA model in the existing generic `SQLiteDynamicTriggerForeignKeyPlan`.
