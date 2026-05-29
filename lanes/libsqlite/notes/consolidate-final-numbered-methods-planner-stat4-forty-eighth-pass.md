2026-05-29T15:11Z - consolidate-final-numbered-methods-planner-stat4-forty-eighth-pass

- Consolidated `SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan` by renaming the numbered production entrypoint and private helpers to canonical descriptive names.
- Migrated the direct focused test to `SQLitePlannerExpressionSkipScanRangeCurrentSourceTest.php` and updated it to call `materialize()`.
- Removed the numbered dependency/status/test labels from this direct planner/STAT4 family; no compatibility shim was left for the removed numbered production entrypoint or its numbered helper methods.
- Verification: `php -l` for the changed production/test PHP files passed; `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionSkipScanRangeCurrentSourceTest.php` passed with 58 assertions; `rg -n "function .*Next[0-9]" lanes/libsqlite/src | wc -l` reports 3304 remaining numbered production helper methods.
- Dependency closure: no new support component needed; this is a source/test consolidation over the existing native PHP expression skip-scan materialization path.
