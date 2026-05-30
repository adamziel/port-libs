# SQLite Covering Expression STAT4 Current Source Next122

Slice: `sqlplanner-covering-expression-stat4-current-source-next122`.

Adds `SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan`, a bounded
planner wrapper for stale prepared statements whose current schema/STAT4 source
can satisfy a lower(option_name) bounded range from a covering expression index.
The plan materializes current/next cursor rows, output-column opcodes, STAT4
matched sample evidence, and no table seek when copied `wp_options` payload
columns are available from the index.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerCoveringExpressionStat4CurrentSourceNext122Test.php`
- `php -l lanes/libsqlite/examples/application-covering-expression-stat4-current-source-next122.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringExpressionStat4CurrentSourceNext122Test.php`
- `php lanes/libsqlite/examples/application-covering-expression-stat4-current-source-next122.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: +72 focused PASS lines in a new lane-scoped test
file. `lane-status.json` records `phpPass` 47728 from the clean worktree
baseline 47656 plus these 72 verified PASS lines; mapped upstream coverage is
unchanged because this is PHP behavior evidence, not a fresh manifest-backed
upstream inventory row.

Non-overlap: this avoids accepted next118 partial expression covering,
next119 ordinary multicolumn covering range/order, next114 partial-collation
STAT4, next103 expression covering ORDER BY, expression ORDER BY execution, and
expression-index range-cost ranking. The new behavior is current-source row
materialization for a bounded covering expression STAT4 range cursor.

Dependency closure: no new support component is needed; the slice reuses native
CREATE INDEX expression parsing, existing STAT4 sample planning, and
lane-local covering cursor diagnostics.
