# sqlplanner-stat4-expression-partial-current-source-next246

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a current-source
planner fence for partial expression indexes backed by `sqlite_stat4`. The slice
reuses the accepted next243 sample-tape validation and adds a second check that
current STAT4 `neq` duplicate cardinality matches the duplicate expression-key
buckets yielded by the current source.

WordPress relevance: copied `wp_options` plugin scans with duplicate
`lower(option_name)` keys now reject stale duplicate cardinality before reusing a
prepared partial expression-index plan.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext246Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next246.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext246Test.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next246.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-current-source-next246 self-test passed`
- `git diff --check -- lanes/libsqlite`

## Delta

Focused `phpPass` expected movement: `128615` to `128687` (`+72`) after clean
integration. Mapped upstream coverage is unchanged.

## Non-overlap

Avoids accepted next243 sample-tape validation, expression `ORDER BY`,
expression-index range-cost ranking, JSON table/source/cursor work, WAL/VFS
durability, B-tree page/freelist work, trigger, UTF/collation, and suite-runner
clusters. This slice only validates current STAT4 duplicate cardinality for
partial expression-index reuse.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
current-source planner materializers and adds lane-local PHP validation over the
current source rows and selected index `stat4Samples`.
