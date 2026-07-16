# sqlplanner-stat4-expression-payload-covering-fence

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
current-source STAT4 expression partial-index fence for `lower(option_name)`
Application option predicates. The slice sits after the accepted current-source
STAT4 grouped LIKE/OR fences and blocks reuse when the current expression
payload rows, STAT4 sample payloads, or covering-column set no longer match the
selected current source.

The Application smoke demonstrates a copied `wp_options` autoload query using a
partial expression index on `lower(option_name)` with STAT4 samples and current
covering payload rows.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialExpressionPayloadCoveringFenceTest.php`
  - `1 test files, 70 assertions, 0 failures`

## Non-Overlap

This avoids accepted STAT4 rowid alias, duplicate fanout, predicate-definition,
grouped OR/LIKE, expression `ORDER BY`, expression-index range-cost, JSON, WAL,
VFS, B-tree, trigger, and UTF clusters. The new behavior is only the
current-source expression-payload/covering-column proof needed before reusing a
STAT4 partial expression index.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
planner/test harness and current-source STAT4 expression partial fixtures.
