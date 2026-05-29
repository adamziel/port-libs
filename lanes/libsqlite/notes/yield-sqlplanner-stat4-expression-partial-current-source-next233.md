# sqlplanner-stat4-expression-partial-current-source-next233

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded current-source STAT4 expression partial-index guard that composes next230 gap-density proof and then validates each in-window `sqlite_stat4` sample rowid against the current row image. Reuse remains ready only when every sample row still exists, its `lower(option_name)` key matches the sample key, and the row still satisfies the partial predicate.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Test.php`
- Result: `1 test files, 69 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next233.php`
- Result: self-test exits `0` with `stat4-expression-partial-current-source-next233-ready`

## Dependency Closure

No new support component is needed. This slice reuses lane-local STAT4 expression partial planner materializers and adds only bounded current-source row-array validation under `lanes/libsqlite/src`.

## Non-Overlap

Avoids accepted next230 gap-density peers, expression `ORDER BY`, expression-index range-cost ranking, JSON table planner/cursor work, WAL/pager/VFS, B-tree, UTF/collation, trigger, PRAGMA, and suite-runner surfaces. This patch is limited to stale `sqlite_stat4` sample rowids that no longer resolve to current partial expression-index rows after source/stat4 changes.
