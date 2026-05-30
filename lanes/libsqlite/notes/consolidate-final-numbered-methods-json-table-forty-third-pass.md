# JSON table numbered method consolidation forty-third pass

Consolidated the early JSON table current-source planner chain on
`SQLiteJsonTablePlan` into stable unsuffixed production entry points:

- `currentSourceIndexedConstraintCost()`
- `currentSourceOrderByConstraint()`
- `currentSourceNestedPathPlanner()`
- `currentSourceIndexedHiddenOrder()`
- `currentSourcePathConstraintPushdown()`
- `currentSourceConstraintOrderByCost()`
- `currentSourceNestedConstraintCost()`
- `currentSourcePathHiddenRowidCost()`
- `currentSourceNestedConstraintOrder()`
- `currentSourceHiddenPathOrderBy()`
- `currentSourceNestedHiddenCost()`
- `currentSourcePathOrderByCost()`
- `currentSourceHiddenGeneratedOrder()`
- `currentSourceNestedPathRowid()`
- `currentSourceGeneratedPathCost()`
- `currentSourceHiddenRowidOrder()`
- `currentSourceGeneratedHiddenCost()`
- `currentSourcePathGeneratedOrder()`

The direct tests and Application examples for this family were migrated to the
stable method names and stable filenames. Behavioral array keys, transition
labels, opcodes, and dependency receipt strings are intentionally unchanged so
existing assertions continue to prove the accepted planner states.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l` on the renamed JSON-table focused tests and examples
- `php tools/run-tests.php` on the renamed focused JSON-table tests
- selected renamed Application example `--self-test` checks
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the
existing native JSON table planner, JSON path handling, JSONB decoder, and
focused PHP runner.

Non-overlap: consolidation-only; no new JSON table behavior, `phpPass`, mapped
coverage, or Application scenario count is claimed.
