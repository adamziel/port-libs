# compound-select-correlated-window-filter-current-source-next126

## Behavior

Adds `SQLiteCompoundWindowFilterCurrentSourcePlan`, a current/next diagnostic
wrapper around native `SQLiteSelectSql` compound SELECT execution. The slice
exercises compound `UNION ALL` arms whose projection contains aggregate window
functions with `FILTER` clauses and correlated subqueries, then reports current
and next row signatures, filtered window aliases, compound arm/order metadata,
and replan reasons.

This avoids accepted plain compound row composition, expression `ORDER BY`,
grouped SELECT text, JSON table SELECT sources, and standalone window helper
clusters. The behavior is parser/executor current-source wiring over copied
`wp_options` and staging rows.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCorrelatedWindowFilterCurrentSourceNext126Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
30 PASS lines
1 test files, 70 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-window-filter-current-source-next126.php --self-test
```

Expected result:

```text
application-compound-window-filter-current-source-next126 self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses native SELECT SQL compound
execution, correlated subquery predicates, aggregate window frame/FILTER
evaluation, and current/next row-array sources.

## Next

A follow-up SQL-exec slice can address the separate multiline `IN (SELECT ...)`
predicate parse gap found during red-first exploration; this patch stays on
the correlated `EXISTS` behavior to remain bounded and non-overlapping.
