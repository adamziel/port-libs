# sqlplanner STAT4 expression partial current-source next254-269 prep

Adds a bounded preparation handoff for planner STAT4 expression partial
current-source next254-269 after accepted next253 payload row-image validation.
The wrapper records sixteen follow-on slice windows, each tied to the yielded
rowid, current row image, projected columns, and payload/current rowid match
needed by the next direct planner continuation.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext254269Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next254-269.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext254269Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next254-269.php --self-test`
- `git diff --check -- lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext254269Test.php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next254-269.php lanes/libsqlite/notes/yield-sqlplanner-stat4-expression-partial-current-source-next254-269.md`

Expected focused movement: `+38` focused assertions in the new lane-scoped
test file. Mapped coverage is unchanged; this is PHP behavior preparation over
already mapped planner STAT4 expression partial current-source inventory.

Dependency closure: no new support component needed. The slice reuses next253
payload-current row proofs and adds a bounded handoff plan for next254-269.

Non-overlap: this prepares next254-269 handoff windows only. It avoids changing
next253 payload row-image validation, next250 predicate implication, page
anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF
clusters.
