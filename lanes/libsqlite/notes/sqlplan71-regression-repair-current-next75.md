# sqlplan71 regression repair current-next75

This slice repairs the rejected sqlplan71 planner surface without repeating the
accepted expression-index range-cost, STAT4 JSON, partial-index proof, or
SELECT SQL expression ORDER BY clusters.

Behavior:

- `SQLiteAnalyzeStatPlanner` now canonicalizes multiple usable constraints on
  the same indexed column before prefix matching.
- Equality and `IN` constraints dominate stale lower/upper range constraints
  for the same column, matching SQLite planner behavior where a point lookup is
  narrower than range terms.
- Paired lower and upper range constraints on the same column are exposed as a
  single `BETWEEN` current range with `rangeConstraints` metadata instead of
  whichever term appeared last.
- Composite index prefix matching still stops after the first range, so later
  equality terms remain residual instead of being incorrectly admitted as a
  trailing search prefix.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAnalyzeStatPlannerCurrentNext75Test.php lanes/libsqlite/tests/SQLiteAnalyzeStatPlannerCorpusTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 186 assertions, 0 failures
```

New focused PASS lines: `+15` from
`SQLiteAnalyzeStatPlannerCurrentNext75Test.php`.

Application smoke:

```text
php lanes/libsqlite/examples/application-analyze-stat-planner-current-next75.php
application-analyze-stat-planner-current-next75
index=wp_options_name
matched=option_name
operator=BETWEEN
bounds=_transient_.._transient_timeout_
estimatedRows=4
detail=SEARCH wp_options USING INDEX wp_options_name (option_nameBETWEEN?)
```

Dependency closure: no new support component is needed. The patch reuses the
existing bounded native PHP stat1 planner and adds only same-column constraint
canonicalization metadata needed for current/next planner regression evidence.

Non-overlap: avoids accepted expression-index range-cost ranking, STAT4 JSON
covering order, partial-index proof, expression ORDER BY, JSON planner,
WAL/VFS, and B-tree apply clusters. This is the narrow stat1 planner regression
repair for same-column equality/IN/range precedence.
