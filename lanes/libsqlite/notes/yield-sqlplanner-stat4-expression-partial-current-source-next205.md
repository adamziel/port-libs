# sqlplanner-stat4-expression-partial-current-source-next205

## Behavior

Adds a current-source STAT4 peer-cardinality fence for partial expression-index
plans. The slice builds on the accepted next203 boundary-sample admission and
requires duplicate `lower(option_name)` peers in a copied `wp_options` LIMIT
window to be covered by current STAT4 `neq` samples before the planner admits
the current partial expression-index scan.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext205Test.php`
  - `1 test files, 56 assertions, 0 failures`
  - `56` focused PASS lines
- Application smoke: `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next205.php`

## Non-Overlap

This avoids accepted next203 STAT4 boundary samples, next200 NOT BETWEEN
residuals, next196 peer ordering, expression ORDER BY, range-cost, JSON, WAL,
VFS, B-tree, trigger, and encoding clusters. It only proves duplicate
expression-key peer cardinality from current STAT4 `neq` samples.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
planner arrays, current-source rows, and STAT4 sample fixtures.
