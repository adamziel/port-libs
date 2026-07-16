# Compound SELECT Window Recursive LIMIT Current Source Next226

## Behavior

Adds a current-source compound SELECT fence for `WITH RECURSIVE` queries whose
recursive queue uses `ORDER BY ... LIMIT ... OFFSET`, then feeds aggregate
window output through `UNION ALL`, `EXCEPT`, `INTERSECT`, final `ORDER BY`, and
final `LIMIT/OFFSET`.

The Application path is copied `wp_options` preview SQL where staged next-source
rows can change aggregate-window source tokens and truncation diagnostics even
when the final `INTERSECT` page admits only recursive dependency rows.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext226Test.php`
  - `1 test files, 358 assertions, 0 failures`
  - `65` PASS lines

## Non-Overlap

Avoids accepted next219 `percent_rank`/`cume_dist` EXCEPT fencing, next217
rank/dense_rank INTERSECT fencing, next213 min/max INTERSECT fencing, next212
group_concat/row_number EXCEPT fencing, next210 row_number/last_value
INTERSECT+EXCEPT fencing, and accepted JSON/WAL/B-tree/VFS clusters. This slice
uses aggregate `sum`/`count` window output through both EXCEPT and INTERSECT.

## Dependency Closure

No new support component is needed. The slice reuses native PHP SELECT SQL
compound execution, recursive queue tracing, aggregate window dispatch,
current-source token fencing, and final LIMIT helpers.
