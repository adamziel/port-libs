# SQLite planner STAT4 expression partial current-source next231

Status: focused PHP behavior growth for
`sqlplanner-stat4-expression-partial-current-source-next231`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
an additive current-source page membership fence for partial expression-index
STAT4 plans. After the accepted next228 sample-row partial-predicate proof, it
recomputes the qualifying current rowset for the query WHERE terms, applies the
selected LIMIT/OFFSET page, and records a VDBE-style
`RecheckCurrentStat4ExpressionPartialPage` cursor opcode only when the selected
page still matches the current source.

WordPress smoke:
`wordpress-sqlplanner-stat4-expression-partial-current-source-next231.php`
models copied `wp_options` plugin-admin pagination over a descending partial
`lower(option_name)` index after ANALYZE/source changes.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Test.php`
  - `1 test files, 70 assertions, 0 failures`

Dependency closure: no new support component is needed. The patch composes
existing lane-local STAT4 expression partial fences, current-source row
materialization, SQL LIKE matching, and bounded page diagnostics.

Non-overlap: avoids accepted next228 sample-row partial-predicate validation,
next226 sample-window validation, grouped LIKE/OR proof, expression ORDER BY,
range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-admission
clusters. The new surface is current qualified-rowset page membership for a
STAT4 partial expression plan.
