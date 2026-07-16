# Compound SELECT Window Recursive LIMIT Current Source UnionExceptDenseRankLimit

## Scope

This slice adds focused current-source coverage for parser-level compound
SELECT output where a bounded recursive CTE feeds a `UNION` distinct arm, a
`dense_rank()` window over copied `wp_options` rows shifts between current and
next sources, and an `EXCEPT` tail plus final `ORDER BY` / `LIMIT ... OFFSET`
decides the admitted page.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionExceptDenseRankLimitTest.php
1 test files, 466 assertions, 0 failures
```

The test file adds 73 PASS cases for the distinct compound/window boundary,
including generated current/next source shifts and stale cursor rejection.

Application smoke:

```text
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-union-except-dense-rank.php
```

## Non-Overlap

Avoids accepted next224 `UNION ALL` / `INTERSECT` / `EXCEPT` `row_number()`
rank-shift coverage, next221 `max` / `sum` window `INTERSECT` coverage,
next190 expression LIMIT coverage, accepted SELECT SQL grouped/subquery/order
clusters, and JSON/WAL/B-tree/VFS surfaces. The narrower new behavior is
`UNION` distinct plus `EXCEPT` with `dense_rank()` output changing the final
compound page after recursive queue `LIMIT/OFFSET`.

## Dependency Closure

No new support component is needed. This reuses lane-local SELECT SQL compound
execution, recursive CTE queue tracing, dense-rank window dispatch, EXCEPT
membership, cursor token fencing, and final LIMIT/OFFSET helpers.
