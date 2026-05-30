# yield-sqlite-select-aggregate-having-flatten-current-next34

Implemented parser/executor support for SQLite implicit single-group aggregate
SELECTs with `HAVING` and no `GROUP BY`.

Focused movement:

- Added `SQLiteGroupedAggregate::summarizeAll()` so aggregate SELECTs over all
  filtered input rows produce one summary row, including empty-rowset
  `count(*) = 0`, `sum(...) IS NULL`, and `total(...) = 0.0` behavior.
- Taught `SQLiteSelectSql` to route aggregate SELECT lists or HAVING clauses
  without `GROUP BY` through an empty-column aggregate group instead of plain
  scalar projection.
- Rewrites aggregate expressions in implicit HAVING predicates, including
  non-projected aggregate functions such as `SELECT count(*) ... HAVING
  sum(bytes) > 0`.
- Added a Application copied `wp_options` smoke for aggregate preflight checks
  without ext/sqlite.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectImplicitAggregateHavingCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Non-overlap:

- Does not repeat accepted parser-level `GROUP BY`/`HAVING` SQL text, composite
  GROUP BY, expression `ORDER BY`, correlated subqueries, JSON table SELECT
  sources, VFS writer/lock/sync, WAL checkpoint/savepoint, or B-tree page move
  clusters.
- This slice covers the distinct SQLite behavior where an aggregate query has
  no explicit `GROUP BY` and `HAVING` filters the single implicit aggregate
  group.

Dependency closure:

- No new support component is needed; this reuses the existing SELECT parser,
  predicate evaluator, projection, and aggregate summary helpers.
