# row-value-update-delete-current-source-next110

Status: focused PHP corpus growth for bounded `UPDATE` / `DELETE` SQL text
execution with SQLite row-value predicates and row-value assignments.

Behavior:

- `SQLiteUpdateDeleteReturningSql` now parses balanced comma lists through row
  tuples instead of splitting inside parentheses.
- `UPDATE ... SET (a,b,...) = (...)` expands into per-column assignment
  callbacks while rejecting arity mismatches and duplicate target columns.
- `WHERE (a,b,...) = (...)`, inequalities, `IN`, and `NOT IN` are evaluated
  with SQLite-style lexicographic row-value comparison and NULL propagation:
  decisive earlier-term mismatches stay true/false, while equal-prefix NULL
  comparisons are unknown.
- `RETURNING`, `ORDER BY`, `LIMIT`, and source-order mutation rows continue to
  use the existing `SQLiteUpdateDeleteLimitPlan` path.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueCurrentSourceNext110Test.php
1 test files, 52 assertions, 0 failures
52 PASS lines

php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueCurrentSourceNext110Test.php
2 test files, 105 assertions, 0 failures
105 PASS lines
```

Application smoke:

```text
php lanes/libsqlite/examples/application-row-value-update-delete-current-source-next110.php --self-test
application-row-value-update-delete-current-source-next110 self-test passed
```

Dashboard delta:

- `phpPass`: `42491 -> 42543` from the 52 newly passing focused PASS lines.
- Mapped coverage is unchanged at `604 / 1589`; this is PHP behavior coverage,
  not a newly claimed upstream-manifest row.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `SQLiteUpdateDeleteReturningSql`, `SQLiteUpdateDeleteLimitPlan`, and row-array
  Application copy/import execution path.

Non-overlap:

- Avoids accepted batch106 DML trigger RETURNING conflict handling, trigger
  savepoint rollback, planner partial-index routing, VDBE distinct/collation,
  B-tree, pager/WAL, JSONB, and PRAGMA clusters.
- Avoids previously accepted row-value comparison and UPDATE/DELETE
  ORDER/LIMIT-only slices. The new surface is row-value predicates and
  row-value assignments inside the current-source `UPDATE` / `DELETE`
  RETURNING SQL executor.
