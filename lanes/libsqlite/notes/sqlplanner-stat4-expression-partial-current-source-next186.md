# SQL planner STAT4 expression partial current-source next186

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next186`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded current-source planner composition for stale prepared `wp_options` partial expression indexes where an `IN` probe stream also carries `LIMIT`/`OFFSET` and covering payload projection.

Behavior covered:

- reuses the accepted next183 STAT4 partial expression `IN` multi-probe row stream;
- applies SQLite-style `LIMIT`/`OFFSET` after probe-order rowid deduplication;
- projects covering payload columns from the current source without table lookup;
- preserves zero-limit and exhausted-offset empty rowsets as ready plans;
- records limit/projection fence signatures and cursor opcodes for the windowed row stream.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next186.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-current-source-next186 self-test passed`

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext186Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next186.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext186Test.php`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next186.php --self-test`
  - `wordpress-sqlplanner-stat4-expression-partial-current-source-next186 self-test passed`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses lane-local STAT4 expression partial equality probes, partial predicate proof, current-source fences, and covering payload materialization.

Non-overlap: avoids accepted next183 unwindowed `IN` multi-probe, next182 single-range `LIMIT` projection, next165 one-sided expression ranges, next180 descending scans, expression `ORDER BY`, expression-index range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and pager clusters. The new surface is only current-source `LIMIT`/`OFFSET` and covering projection over the STAT4 partial expression `IN` row stream.
