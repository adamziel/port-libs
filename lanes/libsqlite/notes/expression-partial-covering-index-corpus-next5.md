# Expression Partial Covering Index Corpus Next5

Status: focused PHP corpus added.

This slice extends `SQLiteSelectExpressionIndexPlan` so an expression-index
constraint may use sibling `AND` terms to prove a partial-index predicate. The
new path covers equality, `IS NOT NULL`, `IN`, `BETWEEN`, range, `AND`, and
`OR` partial predicates while preserving the existing safe expression-column
non-null proof. Covering-column and ORDER BY metadata remain cost inputs; this
slice does not repeat the accepted expression-index range-cost ranking.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionPartialCoveringIndexCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
25 PASS lines
1 test files, 57 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-expression-partial-covering-index.php
```

Dashboard delta: `phpPass` increases by the verified focused PASS-line delta,
`1684 -> 1709`. No new upstream inventory denominator unit is claimed.

Non-overlap: avoids accepted expression-index range-cost ranking, SQL
expression `ORDER BY`, JSON hidden/visible constraints, VFS writer/lock/sync
clusters, WAL rollback/checkpoint/savepoint byte work, and B-tree page move /
overflow freelist clusters.

Dependency closure: no new support component is needed; this reuses the
existing native CREATE INDEX metadata parser and expression-index planner.
