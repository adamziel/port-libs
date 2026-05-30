# JSON Table Indexed Constraint Cost Current Source Next119

## Scope

This slice adds current/next JSON table planner metadata for the selected indexed visible constraint. It preserves existing JSON table row materialization while reporting the selected `id` / `fullkey` / `path` / `key` constraint, scan strategy, indexed row/cost estimates, row-count transitions, and next-source replan reasons.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCostTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-indexed-constraint-cost.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCostTest.php`
- Result: `1 test files, 52 assertions, 0 failures`
- Focused PASS-line delta: `+52`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-indexed-constraint-cost.php --self-test`
- Smoke result: `application-json-table-indexed-constraint-cost self-test passed`
- Root harness: not run - isolated micro-slice

## Non-Overlap

Avoids accepted JSON table cursor/source wiring, hidden constraint extraction, visible constraint pushdown execution, lateral rowid, JSON generated-index/JSONB generated-index, and next113 cost/order metadata. This patch adds only the indexed visible-constraint cost selection layer on top of current-source cost/order planning.

## Dependency Closure

No new support component is needed. The patch reuses native JSON table planning, current-source validation, residual filtering, and row-array ordering.
