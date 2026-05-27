# Yield SQLite Planner Partial Index Proof Current Next24

This slice adds bounded planner proof support for partial indexes where
separate ordinary `AND` terms jointly imply a partial-index range predicate.
The current planner already handled one-term partial proofs; this adds the
SQLite-style case where lower and upper ordinary constraints together prove
partial predicates such as `WHERE option_name BETWEEN '_transient_' AND
'_transient_timeout_zzzz'`.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialIndexProofCurrentNext24Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS ... 53 focused planner partial-index proof cases

1 test files, 115 assertions, 0 failures
```

Expected dashboard movement: `phpPass` increases by the exact focused PASS-line
delta, `8166 -> 8219`, with no mapped-denominator change.

Dependency closure: no new support component is needed. The slice reuses the
existing `SQLiteSelectExpressionIndexPlan`, `SQLiteCreateIndex`, and
`SQLiteIndexPredicate` bounded planner metadata.

Non-overlap: this does not repeat batch21 partial-index surfaces, expression
ORDER BY, expression-index range cost ranking, JSON table source/cursor work,
B-tree overflow/root-collapse/page-move work, or accepted VFS/WAL application
clusters. It is limited to combined partial-index predicate proof in the
planner.
