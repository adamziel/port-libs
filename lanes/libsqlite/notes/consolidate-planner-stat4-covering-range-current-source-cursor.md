# Planner STAT4 Covering Range Current-Source Cursor Consolidation

## Summary

- Consolidated the numbered public entry point into `materializeCurrentSourceCursor()`.
- Renamed the internal numbered helper family to descriptive helper names.
- Renamed the direct focused test and Application smoke away from the numbered filename surface.
- Preserved the canonical production class/file name because it is already unsuffixed by worker number.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4CoveringRangeCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/application-planner-stat4-covering-range-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringRangeCurrentSourceTest.php`
  - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-planner-stat4-covering-range-current-source.php --self-test`
  - `application-planner-stat4-covering-range-current-source-cursor self-test passed`

## Dependency Closure

No new support component is needed. This is a production helper/method consolidation only; it reuses the existing planner and STAT4 covering range planning primitives.
