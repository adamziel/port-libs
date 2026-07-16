# SQLite planner STAT4 expression partial current-source next190

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
current-source fence.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
accepted next175 LIKE-prefix STAT4 partial-expression admission and retains a
`NOT IN` residual fence over the current-source row stream. It rejects excluded
Application option names after current STAT4 prefix admission, preserves SQL NULL
poisoning as a replan case, and rewrites the cursor program result rowids after
the residual check.

Application smoke:

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next190.php`
- Output status: `stat4-expression-partial-current-source-next190-ready`
- Before residual rowids: `[10,40,20,60]`
- After residual rowids: `[10,20]`
- Rejected rowids: `[40,60]`

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext190Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next190.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext190Test.php`
- Result: `1 test files, 76 assertions, 0 failures`
- Expected PASS delta: 76 PASS lines for one focused test file.

Dependency closure: no new support component is needed; this reuses lane-local
STAT4 expression partial planning, LIKE-prefix admission, current-source row
materialization, and residual predicate fencing.

Non-overlap: avoids accepted next184 IN-predicate implication, next187 NOT LIKE
residuals, next175 LIKE-prefix admission, expression ORDER BY, range-cost,
JSON, WAL, VFS, B-tree, trigger, and suite evidence clusters. This slice only
adds `NOT IN` residual exclusion after current-source STAT4 partial expression
index admission.
