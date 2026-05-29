# Consolidate final numbered production suffix cleanup: thirty-seventh pass

- Scope: planner covering partial range current-source consolidation.
- Production cleanup: renamed the numbered planner covering partial range entry point to `materialize()` and removed the numeric worker suffix from the internal helper method names.
- Caller cleanup: updated `SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan` to call the canonical method.
- Direct tests/examples: moved the direct test and WordPress example to unsuffixed filenames and removed direct numbered references from their scenario strings and index names.
- Exact user-named 150 suffix scan: clean in `lanes/libsqlite/src`.
- Production numbered filename/class audit: `0`.
- Remaining numbered production method-line audit: `4118`.
- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringPartialRangeCurrentSourcePlanTest.php lanes/libsqlite/tests/SQLitePlannerPartialRangeCoveringCurrentSourceTest.php` -> `2 test files, 119 assertions, 0 failures`.
- Examples: `php lanes/libsqlite/examples/wordpress-planner-covering-partial-range-current-source.php --self-test` and `php lanes/libsqlite/examples/wordpress-planner-partial-range-covering-current-source.php --self-test` passed.
- Dependency closure: no new support component needed; this pass only renames existing native PHP planner methods and direct callers.
