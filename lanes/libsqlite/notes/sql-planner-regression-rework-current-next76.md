# SQL Planner Regression Rework Current Next76

Adds a disjoint `SQLiteJoinOrderPlan` helper for bounded stat1-style nested
loop ordering. The planner reuses existing `SQLiteAnalyzeStatPlanner` access
decisions, then tests connected join permutations so outer-loop filters and
inner join-equality probes choose stable plans for copied Application
`wp_posts`/`wp_postmeta`/taxonomy previews.

This intentionally avoids the rejected `sqlplan71` expression-index/stat4
surface and the queued current-next74/current-next75 rework helpers. It does
not modify `SQLiteSelectExpressionIndexPlan` or `SQLiteAnalyzeStatPlanner`.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJoinOrderPlannerCurrentNext76Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 81 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-join-order-planner-current-next76.php
```

Status delta: adds 50 focused PASS lines in
`SQLiteJoinOrderPlannerCurrentNext76Test.php`; `phpPass` moves from 28917 to
28967 for this isolated current-base patch. Mapped upstream coverage is
unchanged because this is behavior coverage over existing planner inventory,
not a new denominator row.

Dependency closure: no new support component is needed. This reuses native PHP
stat1 planner primitives already in `lanes/libsqlite/src`.

Non-overlap: avoids accepted expression-index range costs, SQL expression
`ORDER BY`, SELECT/JOIN text execution, JSON table source/cursor/constraint
work, WAL/VFS/B-tree accepted clusters, and queued sqlplan74/sqlplan75 rework
surfaces.
