# SQLite planner STAT4 expression partial current-source next203

Status: focused PHP behavior growth for a STAT4 expression partial-index
planner current-source boundary fence.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`
composes the accepted next196 peer-order fence and adds a LIMIT/OFFSET boundary
sample check. A stale prepared partial expression-index scan is admitted only
when the selected current-source window is non-empty, the first and last
expression keys are present in current `sqlite_stat4` samples, sample counters
remain monotonic, and the sample row payload still evaluates to the expected
expression key.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next203.php`
- status `stat4-expression-partial-current-source-next203-ready`
- matched rowids `[30, 50, 20, 21, 22]`
- boundary keys `["plugin_seo", "plugin_forms"]`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext203Test.php`
- `1 test files, 60 assertions, 0 failures`
- expected PASS delta: 60 PASS lines for one focused test file.

Dependency closure: no new support component is needed; this reuses lane-local
current-source STAT4 expression partial planning and adds a bounded boundary
sample admission check.

Non-overlap: avoids accepted next196 peer-order, next195 partial-WHERE,
expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding
clusters. This slice only proves selected LIMIT/OFFSET window boundaries are
bracketed by current STAT4 expression samples before admitting the partial
expression-index scan.
