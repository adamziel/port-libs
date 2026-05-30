# sqlplanner-stat4-expression-partial-current-source-next230

## Behavior

Adds a bounded current-source STAT4 partial expression-index gap-density proof for copied `wp_options` range scans. The new planner layer composes the accepted next226 sample-window proof, then validates peer rows that satisfy the partial predicate but are not represented as `sqlite_stat4` sample rowids. Anchored peer gaps can continue on the current cursor; unanchored in-range gaps force a reprepare.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Test.php`
- Result: `1 test files, 66 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next230.php`
- Result: self-test exits `0` with `stat4-expression-partial-current-source-next230-ready`

## Dependency Closure

No new support component is needed. This slice reuses the current native PHP STAT4 partial expression planner chain and adds only bounded row-array proof logic under `lanes/libsqlite/src`.

## Non-Overlap

Avoids accepted next226 sample-window rows, next225-next227 batch200 behavior, expression ORDER BY, expression-index range-cost ranking, JSON table planner/cursor work, WAL/pager/VFS, B-tree, trigger, UTF/collation, and suite-runner surfaces. This patch is limited to partial expression-index peer rows in gaps between STAT4 samples.
