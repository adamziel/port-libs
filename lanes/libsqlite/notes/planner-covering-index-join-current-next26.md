# Planner Covering Index Join Current Next26

This slice adds bounded current-row/next-loop covering-index planning to
`SQLiteCoveringIndexPlan`.

Behavior covered:

- `chooseJoin()` / `rankedJoinPlans()` recognize inner-table indexed columns
  constrained by the current outer row, such as
  `m.option_id = o.option_id`.
- The planner records `joinLoop: current-next`, the target alias, deferred
  equality columns, and outer-column dependencies for the next cursor loop.
- Current-row equalities compose with literal equality, `IN`, `BETWEEN`,
  range, and `IS NOT NULL` constraints to form equality/range prefixes.
- Covering, order satisfaction, row estimates, partial-index implication,
  and deterministic ranking reuse the existing covering-index cost model.
- Unknown outer-column joins and target-to-target column comparisons are
  ignored rather than becoming invalid literal constraints.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringIndexJoinCurrentNext26Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-planner-covering-index-join-current-next26.php --self-test
```

Output:

```text
application-planner-covering-index-join-current-next26 self-test passed
```

Status delta: +51 focused `TestRunner` PASS cases in
`SQLitePlannerCoveringIndexJoinCurrentNext26Test.php`; `phpPass` moves from
8739 to 8790.

Non-overlap: avoids accepted batch23 partial-index WHERE implication,
accepted expression-index range costs, SQL JOIN text execution, derived-table
materialization, JSON table joins/cursors/constraints, VFS/WAL/B-tree storage
clusters, and scalar ORDER/GROUP/subquery executor work. This slice is limited
to covering-index planner selection for a next joined table whose lookup key
comes from the current outer row.

Dependency closure: no new support component is needed. The slice reuses the
existing CREATE INDEX parser, partial-index predicate implication, and
covering-index cost/ranking model.
