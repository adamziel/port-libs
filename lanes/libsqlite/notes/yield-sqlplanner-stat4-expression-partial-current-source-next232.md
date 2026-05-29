# SQLite planner STAT4 expression partial current-source next232

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
slice on current-source sqlite_stat4 counter admission.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
the accepted next228 sample-row partial-predicate fence and adds a current-source
counter validation step for the selected partial expression index. The fence
recomputes `neq`, `nlt`, and `ndlt` from rows that satisfy the current
partial-index predicate, including rows later filtered by residual query terms,
and rejects stale STAT4 counter rows before a yielded WordPress-style
`wp_options` cursor can keep using the partial expression index.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next232.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Test.php`
  - Result: `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next232.php --self-test`
  - Result: `stat4-expression-partial-current-source-next232-ready`

WordPress path: the example models copied autoloaded plugin options where a
prepared partial expression-index scan may only be reused after ANALYZE/schema
churn if current sqlite_stat4 cardinalities still match the current partial
index contents.

Dependency closure: no new support component is needed; this reuses the native
current-source STAT4 expression partial planner and adds bounded PHP
cardinality validation.

Non-overlap: avoids accepted next228 sample-row partial-predicate validation,
next224 sample-order validation, expression ORDER BY, range-cost ranking, JSON,
WAL, VFS, B-tree, trigger, and UTF clusters. This slice only verifies current
sqlite_stat4 counter cardinalities for partial expression-index samples.
