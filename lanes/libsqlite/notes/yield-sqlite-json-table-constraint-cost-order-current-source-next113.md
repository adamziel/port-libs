# JSON Table Constraint Cost Order Current Source Next113

## Scope

This slice adds current/next JSON table planner metadata for constraint cost and ORDER BY behavior. It preserves the existing current-source row materialization while making the xBestIndex-style decision explicit: rowid/id ASC order streams from the JSON table cursor, non-rowid order uses a bounded sorter cost, single-row LIMIT scans avoid a sorter, and changed current/next source rows report cost/order replan reasons.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintCostOrderCurrentSourceNext113Test.php`
- Result: `1 test files, 58 assertions, 0 failures`
- Focused PASS-line delta: `+58`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-constraint-cost-order-current-source-next113.php --self-test`
- Root harness: not run - isolated micro-slice

## Non-Overlap

Avoids accepted parser-level JSON table SELECT source/cursor wiring, hidden constraints, visible constraints, lateral rowid, hidden rowid rebasing, JSON generated-index, JSONB generated-index, and batch107/108 JSON table hidden rowid work. This patch only adds cost/order current-source transition metadata on top of the existing planner.

## Dependency Closure

No new support component is needed. The patch reuses native JSON table planning, residual filtering, current-source validation, and row-array ordering.
