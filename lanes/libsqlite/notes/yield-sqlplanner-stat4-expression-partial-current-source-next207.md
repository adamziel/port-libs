# sqlplanner-stat4-expression-partial-current-source-next207

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a current-source planner fence for STAT4 expression partial indexes. The slice reuses the accepted next206 OR-arm implication planner path, then validates every current `sqlite_stat4` sample rowid against the current row image and the selected partial-index WHERE predicate before reusing the current-source expression partial index.

This blocks a stale post-ANALYZE sample set from making a partial index look admissible or cheaper when one sample now points at a row outside the partial predicate, or at a rowid that no longer exists.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext207Test.php`

Expected focused growth: 64 TestRunner PASS lines in a new lane-scoped test file.

WordPress smoke:

`php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next207.php`

## Non-overlap

Avoids accepted next206 OR-arm implication, next202 partial predicate definition fingerprints, expression ORDER BY, expression-index range-cost ranking, JSON table, WAL, VFS, B-tree, trigger, and UTF clusters. This slice is only about current-source STAT4 sample membership in the partial expression-index predicate.

## Dependency Closure

No new support component needed. The implementation composes existing current-source STAT4 expression partial planner arrays and lane-local PHP validation logic.
