# SQLite planner OR partial covering current next34

## Behavior

Adds bounded OR-clause planning for expression indexes where each OR arm must
independently prove a partial-index WHERE predicate and cover the requested
output columns. The helper reports arm order, selected root pages, unique index
names, summed row estimates, union dedupe requirements, and rejects the whole
OR plan when any arm is unsafe or non-covering.

This targets SQLite's OR optimization shape without repeating accepted
expression-index range-cost ranking, partial skip-scan proof, SQL expression
ORDER BY, or parser-level SELECT text dispatch.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerOrPartialCoveringCurrentNext34Test.php`
- Result: `1 test files, 69 assertions, 0 failures`

## Application smoke

- `php lanes/libsqlite/examples/application-planner-or-partial-covering-current-next34.php`
- Reports copied `wp_options` OR predicates where every arm can be served by a
  safe partial covering expression index without a table b-tree fetch.

## Dependency closure

No new support component is needed. The slice reuses existing
`SQLiteSelectExpressionIndexPlan`, `SQLiteCreateIndex`, and partial-predicate
proof helpers.

## Non-overlap

Avoided accepted partial skip-scan current-next28, partial-index proof
current-next24, expression-index range-cost ranking, SQL expression `ORDER BY`,
JSON table planner/source/cursor clusters, VFS/WAL transaction apply clusters,
and B-tree page-move/freeblock/freelist clusters.
