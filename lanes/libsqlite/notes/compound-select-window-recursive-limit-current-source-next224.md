# Compound SELECT Window Recursive LIMIT Current Source Next224

## Scope

This slice adds focused current-source coverage for a mixed compound SELECT
chain where recursive CTE rows and window-ranked `wp_options` rows flow through
`UNION ALL`, `INTERSECT`, and `EXCEPT` before the final `ORDER BY` /
`LIMIT ... OFFSET` page is admitted.

## Non-overlap

- Avoids accepted next218 `UNION ALL` + `INTERSECT` rank-shift coverage.
- Avoids accepted next190 expression-valued recursive/final LIMIT coverage.
- Avoids accepted JSON table, WAL, B-tree, VFS, grouped SELECT, expression
  ORDER BY, and upstream-runner surfaces.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext224Test.php
```

The test file adds 73 focused PASS cases for the new mixed compound boundary,
including generated current/next source rank shifts and stale cursor rejection.

Application smoke:

```text
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next224.php
```

## Dependency Closure

No new support component is needed. The slice reuses native SELECT SQL compound
execution, recursive queue LIMIT/OFFSET tracing, `row_number()` window output,
`INTERSECT` / `EXCEPT` membership, and final LIMIT helpers.
