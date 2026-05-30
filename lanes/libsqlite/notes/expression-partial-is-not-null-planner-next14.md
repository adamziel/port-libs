# Expression Partial IS NOT NULL Planner Next14

Status: focused PHP corpus added.

This slice extends `SQLiteSelectExpressionIndexPlan` so sibling ordinary
`IS NOT NULL` terms can prove a partial expression index predicate. It covers
`lower()`, `upper()`, `length()`, and `CAST(... AS INTEGER)` expression
constraints across point, range, `IN`, `BETWEEN`, reversed operands,
`AND`/`OR` partial predicates, and rejection edges.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionPartialIsNotNullCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
25 PASS lines
1 test files, 122 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-expression-partial-is-not-null.php
```

Dashboard delta: `phpPass` increases by the verified focused PASS-line delta,
`3796 -> 3821`. No new upstream inventory denominator unit is claimed.

Non-overlap: avoids accepted expression-index range-cost ranking, SQL
expression `ORDER BY`, JSON table source/cursor/hidden/visible constraints,
VFS writer/lock/sync/rollback clusters, WAL byte/checkpoint/savepoint work,
and B-tree page-move/root-collapse/overflow freelist clusters.

Dependency closure: no new support component is needed; this reuses the
existing native CREATE INDEX metadata parser and expression-index planner.
