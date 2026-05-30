# SQL Planner STAT4 Expression Partial Current Source Next200

## Behavior

Adds a bounded current-source planner materializer for partial expression indexes
that already pass the accepted next175 STAT4 LIKE-prefix admission, then retain
`NOT BETWEEN` residual checks over the current row stream.

The slice models copied `wp_options` plugin scans where an `ANALYZE` refresh
changes the STAT4 prefix window and a prepared statement must recheck residual
range predicates before returning rows from a covering partial
`lower(option_name)` index.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext200Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next200.php --self-test`
- Syntax/lint: changed PHP files linted with `php -l`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This extends accepted next175 LIKE-prefix STAT4 partial expression scans with a
`NOT BETWEEN` residual fence. It avoids accepted next187 `NOT LIKE` residuals,
next194 `IS DISTINCT FROM` residuals, expression `ORDER BY`, range-cost, JSON,
WAL, VFS, and B-tree clusters.

## Dependency Closure

No new support component is needed. The slice reuses lane-local expression
normalization, partial predicate implication, STAT4 prefix-window admission, and
current-source row materialization.
