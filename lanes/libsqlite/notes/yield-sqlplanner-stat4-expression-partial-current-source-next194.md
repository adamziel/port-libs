# SQLite planner STAT4 expression partial current-source next194

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
current-source fence.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
accepted next175 LIKE-prefix STAT4 partial-expression admission and retains
NULL-safe `IS DISTINCT FROM` residual checks over the current-source row stream.
It rejects a current Application option row whose expression key equals the
forbidden plugin name and separately rejects the row whose payload value is SQL
NULL when the residual is `option_value IS DISTINCT FROM NULL`.

Application smoke:

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next194.php`
- Output status: `stat4-expression-partial-current-source-next194-ready`
- Before residual rowids: `[11,44,22,66]`
- After residual rowids: `[11,22]`
- Rejected rowids: `[44,66]`

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext194Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next194.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext194Test.php`
- Result: `1 test files, 77 assertions, 0 failures`
- Expected PASS delta: 77 PASS lines for one focused test file.

Dependency closure: no new support component is needed; this reuses lane-local
STAT4 expression partial planning, LIKE-prefix admission, current-source row
materialization, and residual predicate fencing.

Non-overlap: avoids accepted next190 NOT IN residuals, next184 IN-predicate
implication, next187 NOT LIKE residuals, next175 LIKE-prefix admission,
expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and suite
evidence clusters. This slice only adds NULL-safe `IS DISTINCT FROM` residual
exclusion after current-source STAT4 partial expression-index admission.
