# SQLite Expression Index Partial Current Source Next121

## Behavior

Adds `SQLiteExpressionIndexPartialCurrentSourceNextPlan`, a bounded planner
handoff for current-source reprepare of partial expression indexes over copied
WordPress `wp_options` rows.

The slice covers:

- current-source selection when schema cookie or index signature changes;
- `lower(option_name) COLLATE NOCASE` partial expression indexes;
- OR/AND partial predicate implication (`autoload = 'yes' OR blog_id = 0`);
- point and `IN` expression constraints;
- current-source row materialization, rowid/key ordering, current/next cursor
  pairs, and sorter fallback when ORDER BY is not satisfied.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionIndexPartialCurrentSourceNext121Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-expression-index-partial-current-source-next121.php
```

Expected dashboard movement: `phpPass +63` from the focused lane test. No new
manifest-backed upstream denominator row is claimed.

## Non-Overlap

This avoids accepted expression-index range-cost ranking, covering ORDER BY,
STAT4 collation/current-source, JSON generated-index, and expression ORDER BY
slices. It focuses on partial expression-index current-source materialization
with OR/AND partial predicate proof.

## Dependency Closure

No new support component is needed. The patch composes existing native
`SQLiteCreateIndex` expression/partial predicate parsing with a bounded
current-source planner and row materializer.
