# JSON table numbered methods twelfth pass

Consolidated the early JSON-table planner comparison entrypoints in
`SQLiteJsonTablePlan` into stable descriptive method names:

- `constraintPlannerComparison`
- `lateralConstraintPlannerComparison`
- `lateralRowidComparison`
- `currentSourceConstraintPlanner`
- `currentSourceConstraintCostOrder`

The direct helper names and dependency markers for the current-source and
cost-order planner paths were also renamed to unsuffixed forms. Direct
tests/examples now call the stable entrypoints; no numbered compatibility
methods were left in production source for this family.

Verification:

- `php -l` on `SQLiteJsonTablePlan.php`, fourteen changed/affected JSON-table
  tests, and six changed JSON-table examples: passed.
- `php tools/run-tests.php` on the fourteen changed/affected focused
  JSON-table tests: passed with `14 test files, 839 assertions, 0 failures`.
- The six changed Application JSON-table examples passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: reuses the existing native JSON table planner, lateral host
row materialization, current-source row planner, JSONB decoder, and test
runner. No new support component is needed.
