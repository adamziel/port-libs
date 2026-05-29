# Planner STAT4 Current-Source Range Consolidation

## Scope

- Consolidated the numbered `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` range production entry point into `materializeStat4CurrentRange()`.
- Renamed the associated private helper family to `*Stat4CurrentRange`.
- Migrated the direct focused test and WordPress smoke away from numbered filenames and references.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` - pass
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeTest.php` - pass
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-range.php` - pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeTest.php` - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-range.php --self-test` - pass

## Dependency Closure

No new support component is needed. This is a naming/helper consolidation only; it preserves the existing lane-local STAT4 expression range behavior, partial predicate implication, and current-source row diagnostics.

## Next

Continue removing remaining numbered production helpers in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`, starting with the nearby `next165+` wrapper family.
