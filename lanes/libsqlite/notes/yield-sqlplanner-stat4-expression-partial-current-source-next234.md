# SQLite planner STAT4 expression partial current-source next234

Status: focused PHP behavior growth for
`sqlplanner-stat4-expression-partial-current-source-next234`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`,
an additive current-source histogram fence for partial expression-index STAT4
plans. After accepted next231 page membership validation, it recomputes the
current qualifying rowset and verifies every current `sqlite_stat4` sample's
first-column `neq`, `nlt`, and `ndlt` counts against that rowset before reusing
the plan. Stale cardinality now returns
`requires-current-source-stat4-histogram-reprepare` even when the selected page
still materializes.

WordPress smoke:
`wordpress-sqlplanner-stat4-expression-partial-current-source-next234.php`
models copied `wp_options` plugin-admin pagination over a descending partial
`lower(option_name)` index where duplicate plugin option names must preserve
STAT4 peer counts after ANALYZE/source changes.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext234Test.php`
  - `1 test files, 67 assertions, 0 failures`

Dependency closure: no new support component is needed. The patch composes
existing lane-local STAT4 expression partial fences, current-source row
materialization, SQL LIKE matching, and bounded histogram diagnostics.

Non-overlap: avoids accepted next231 current page membership validation,
next228 sample partial-predicate validation, expression ORDER BY,
range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-admission
clusters. The new surface is current `neq`/`nlt`/`ndlt` histogram cardinality
validation for a STAT4 partial expression plan.
