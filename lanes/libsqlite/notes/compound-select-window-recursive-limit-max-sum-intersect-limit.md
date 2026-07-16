# Compound SELECT Numbered Method Consolidation Sixty-First Pass

## Consolidation

- Consolidated the remaining max/sum aggregate-window `INTERSECT` compound
  SELECT helper family in
  `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` from numbered
  private helpers into descriptive `*MaxSumIntersectLimit()` helpers.
- Renamed the direct focused test and Application smoke to descriptive
  max-sum-intersect LIMIT paths.
- Preserved the scenario coverage: recursive queue `LIMIT ... OFFSET`,
  `max()`/`sum()` window arms, `UNION ALL`, `INTERSECT`, `EXCEPT`, final
  `ORDER BY ... LIMIT ... OFFSET`, stale cursor rejection, and generated
  copied `wp_options` boundary cases.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitMaxSumIntersectLimitTest.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-max-sum-intersect-limit.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitMaxSumIntersectLimitTest.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-max-sum-intersect-limit.php --self-test`

## Non-Overlap

- Avoids accepted compound SELECT row composition, grouped SELECT text,
  expression-limit, lead/nth_value, rank/dense_rank, and older
  zero-limit/EXCEPT-only recursive window slices.
- Does not touch JSON, WAL, B-tree, VFS, PRAGMA, trigger, or encoding surfaces.

## Dependency Closure

- No new support component is needed. The slice reuses existing lane-local SELECT SQL compound execution, recursive CTE queue tracing, aggregate window dispatch, compound `INTERSECT`/`EXCEPT`, current-source token fencing, and final LIMIT helpers.
