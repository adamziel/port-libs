# sqlplanner-stat4-expression-partial-current-source-next241

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a current-source
STAT4 partial-expression planner fence that layers on the accepted next238 covering
payload validation. The new fence rechecks each yielded current rowid against the
full residual WHERE terms, so a covering partial expression index is not admitted
when a row still has fresh payload bytes but no longer satisfies residual predicates
such as `blog_id = 1` or `option_name LIKE 'plugin_%'`.

## Evidence

Focused verification command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Test.php`

Expected focused growth: 60 PASS lines for the new next241 test file.

WordPress smoke:

`php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next241.php`

## Non-Overlap

This avoids accepted next238 covering payload staleness, next234 histogram
validation, expression `ORDER BY`, range-cost ranking, JSON table planner work,
WAL/VFS durability, B-tree freeblock/freelist, trigger, and UTF/collation
clusters. The slice is only residual WHERE validation for current-source STAT4
partial expression index rowids.

## Dependency Closure

No new support component is needed. The implementation reuses existing
lane-local STAT4 expression partial planner metadata and current-source row arrays.
