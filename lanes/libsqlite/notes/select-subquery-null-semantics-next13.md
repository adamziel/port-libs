# SELECT Subquery NULL Semantics Next13

Slice: `yield-sqlite-exists-in-notin-null-semantics-next13`

Added `SQLiteExistsInNullSemanticsNext13Test.php` with focused upstream-style
coverage for SQLite three-valued `EXISTS`, `IN`, and `NOT IN` behavior:
empty RHS lists/subqueries, NULL LHS values, NULL-bearing RHS values, correlated
subqueries whose projected rows are NULL-only, and row-value `IN` / `NOT IN`
subqueries.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteExistsInNullSemanticsNext13Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

PASS delta: `+60` verified PASS lines. `lane-status.json` `phpPass` moves from
`3796` to `3856`. `benchmarkDenominator.mapped` is unchanged because this is
focused PHP corpus coverage for an already mapped SELECT/subquery predicate
surface rather than a newly hydrated upstream inventory unit.

Application smoke:

```text
php lanes/libsqlite/examples/application-select-subquery-null-semantics.php
```

Non-overlap: this does not repeat accepted broad correlated subquery execution,
subquery flattening with joined inner sources, scalar subquery expressions,
SELECT JOIN/GROUP/ORDER text dispatch, JSON table planner/cursor work, or the
accepted VFS/WAL/B-tree/encoding clusters. The new cases isolate SQLite's
NULL-sensitive truth table for `EXISTS`, `IN`, `NOT IN`, and row-value
subquery membership.

Dependency closure: no new support component is needed; this reuses the
existing native PHP SELECT SQL planner/executor and predicate evaluator.

Next task: extend the current-source SELECT executor toward non-overlapping
planner/result gaps such as quantified comparison predicates or additional
subquery result-shape guards if they are not already accepted upstream.
