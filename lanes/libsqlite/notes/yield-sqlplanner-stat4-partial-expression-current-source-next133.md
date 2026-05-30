# sqlplanner-stat4-partial-expression-current-source-next133

Adds `SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan` for a bounded
current-source planner edge where a prepared partial expression-index STAT4 scan
survives schema/stat4/row-generation churn. The plan reparses against the
current source, admits current inserted/updated covering rows, and blocks
prepared payload rows that were deleted before the cursor tape is consumed.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCurrentSourceNext133Test.php`
- `php lanes/libsqlite/examples/application-stat4-partial-expression-current-source-next133.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCurrentSourceNext133Test.php`
- `php -l lanes/libsqlite/examples/application-stat4-partial-expression-current-source-next133.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement after integration is +59 focused libsqlite PASS
lines from the new test file. No new support component is needed; the slice
reuses native expression-index parsing, STAT4 planning, and current-source
covering cursor diagnostics.

Non-overlap: avoids accepted range-cost, expression ORDER BY, subquery-covering,
next122 covering-row materialization, JSON table, WAL/VFS, and B-tree clusters.
