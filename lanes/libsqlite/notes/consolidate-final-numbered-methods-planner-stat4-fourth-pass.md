# Consolidate final numbered planner STAT4 methods, fourth pass

This consolidation removes the `materializeNext558573()` production entrypoint
and its direct numbered helper methods from
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`. The 558-573 STAT4
handoff now uses the descriptive
`materializeStat4ExpressionPartialPreparedHandoff()` entrypoint plus shared
prepared-handoff helpers. The immediate 574-589 follow-on now consumes that
descriptive method instead of the removed numbered wrapper.

Direct test and WordPress smoke coverage for the 558-573 handoff were migrated
to the descriptive production entrypoint. The externally observed plan payload
is preserved, including the existing `stat4Next558573PreparationFence` keys,
cursor opcode payload, dependency marker, and status string.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext558573Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next558-573.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext558573Test.php`:
  `1 test files, 39 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext574589Test.php`:
  `1 test files, 39 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next558-573.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This is a
consolidation-only patch over existing planner STAT4 handoff data structures.

Non-overlap: this pass only targets the planner STAT4 expression-partial
558-573 numbered method/helper wrapper surface and the direct 574-589
follow-on call. It does not introduce functional coverage work or touch JSON,
WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or encoding behavior.
