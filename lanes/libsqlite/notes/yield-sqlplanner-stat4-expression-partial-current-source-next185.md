# SQLite planner STAT4 expression partial current-source next185

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
current-source fence.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
the accepted next182 LIMIT/OFFSET covering window and adds a stale-sample
provenance fence. A prepared partial expression-index plan is only admitted when
the current STAT4 sample signature changed, every selected window rowid exists
in the current source, and the cursor program records a current STAT4 sample
fence.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next185.php --self-test`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next185 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Test.php`
- `1 test files, 65 assertions, 0 failures`
- expected PASS delta: 65 PASS lines for one focused test file.

Dependency closure: no new support component is needed; this reuses lane-local
STAT4 expression partial planning, current-source row materialization, and
covering window projection.

Non-overlap: avoids accepted next182 LIMIT/OFFSET covering windows, next180
descending scans, next169 cost fences, expression ORDER BY, JSON, WAL, VFS,
B-tree, trigger, and suite evidence clusters. This slice only rejects stale
prepared STAT4 samples when current-source window rowids must be proven against
current rows.
