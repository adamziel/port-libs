# SQL planner STAT4 expression partial current-source next212

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next212`.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` layers on
the accepted current-source STAT4 expression partial grouped-OR fence and adds a
separate grouped partial-arm proof for `LIKE` prefix predicates. The planner
keeps the accepted next209 row stream for base range/materialization, then
proves the query implies the complete grouped `LIKE` arm and rechecks selected
payload rows before admitting stale prepared STAT4 expression-index reuse.

Application smoke:

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next212.php`
- Result: `stat4-expression-partial-current-source-next212-ready` with rowids
  `[30, 50, 20, 21, 22]`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next212.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext212Test.php`
- Result: `1 test files, 69 assertions, 0 failures`.

Dependency closure: no new support component needed; this composes existing
native PHP STAT4 expression partial planning and adds lane-local `LIKE` prefix
proof/recheck logic.

Non-overlap: avoids accepted next209 grouped OR arm proof, next206 single-term
OR proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and
UTF clusters. This slice only covers grouped partial arms containing a `LIKE`
prefix predicate.
