# SQLite planner STAT4 expression partial current-source next209

Status: focused PHP behavior growth for a STAT4 expression partial-index
planner current-source handoff.

Behavior: adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`
for the current-source planner boundary where a changed partial expression
index contains grouped OR arms. The plan is admitted only when one complete
current partial-index OR arm is implied by the query terms and every selected
current-source row satisfies the grouped OR predicate before the STAT4 partial
expression cursor is reused.

Application smoke: `application-sqlplanner-stat4-expression-partial-current-source-next209.php`
models copied multisite `wp_options` plugin diagnostics where the current
partial expression index uses `(blog_id = 1 AND autoload = 'yes') OR
autoload = 'critical'`. The smoke proves the multisite blog/autoload arm before
reusing the current-source STAT4 window.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next209.php`
  - JSON output with `status: stat4-expression-partial-current-source-next209-ready`

Dashboard delta: focused PHP PASS-line growth is `+65`. Mapped upstream
coverage is unchanged because this is behavior coverage over existing
STAT4/partial-index planner inventory rather than a newly mapped upstream
manifest row.

Dependency closure: no new support component is needed. The slice reuses the
lane-local current-source STAT4 expression partial predicate fences, row
payload materialization, and cursor-program diagnostics.

Non-overlap: avoids accepted next206 single-term partial OR predicate fencing,
next202 partial definition fencing, next200 NOT BETWEEN residuals, expression
ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF
clusters. The new surface is complete grouped OR-arm proof for current-source
partial expression indexes.
