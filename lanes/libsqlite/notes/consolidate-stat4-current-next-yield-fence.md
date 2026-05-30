# STAT4 Current/Next Yield Fence Consolidation

Consolidated the STAT4 expression partial current/next yield-fence production
entry into `materializeCurrentNextYieldFence()` and renamed its private helper
methods to descriptive unsuffixed names.

The direct focused test and WordPress smoke filenames were migrated to stable
descriptive names. Existing `next217` status strings, dependency markers,
array keys, opcode modes, and proof text are preserved as observable evidence
for downstream tests and handoff provenance.

Dependency closure: no new support component is needed; this only renames a
production entry/helper surface inside the existing STAT4 expression partial
planner implementation.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentNextYieldFenceTest.php`
  passed.
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-next-yield-fence.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentNextYieldFenceTest.php`
  passed with `1 test files, 68 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  passed with `133 test files, 7543 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-next-yield-fence.php`
  passed and emitted the expected ready plan with next lookahead rowid `40`.
