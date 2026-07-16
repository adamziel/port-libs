# yield-sqlite-select-compound-recursive-materialize-current-next36

Status delta: adds bounded `SQLiteSelectSql` execution for compound recursive
CTE bodies with multiple non-recursive anchor arms followed by one or more
recursive arms. The recursive queue now materializes all current-row recursive
arms in arm order, applies shared `UNION`/`UNION ALL` duplicate behavior,
honors final recursive `ORDER BY` and `LIMIT`/`OFFSET`, and rejects malformed
recursive arm ordering or mixed recursive set operators.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCompoundRecursiveMaterializeCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 85 assertions, 0 failures
65 PASS lines
```

Regression verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteMaterializedCurrentNext26Test.php lanes/libsqlite/tests/SQLiteRecursiveCteCycleDmlEdgeTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 104 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-compound-recursive-materialize-current-next36.php
[
    "siteurl",
    "home",
    "blogname",
    "_transient_feed",
    "_site_transient_update_plugins",
    "rewrite_rules"
]
```

Non-overlap: this avoids accepted recursive CTE limit/queue behavior,
recursive CTE DML current-source behavior, SELECT SQL compound top-level row
composition, SELECT SQL subqueries, derived-table materialization, JSON table
source/cursor/constraint work, WAL/VFS/B-tree storage clusters, and batch30
correlated flattening. The new behavior is specifically compound recursive CTE
materialization where multiple anchor and recursive SELECT/VALUES arms share
one current recursive queue.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP `SQLiteSelectSql`, `SQLiteSelectCompound`,
`SQLiteSelectQuery`, predicate, bind, and result helpers.
