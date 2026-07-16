# compound-select-window-recursive-limit-current-source-next218

- Added `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` for a bounded current-source replay fence where a recursive CTE with `LIMIT/OFFSET` feeds a compound `UNION ALL` arm before `INTERSECT`, and a next-source `wp_options` row shifts `row_number()` ranks across the final `LIMIT/OFFSET` page.
- Focused tests cover metadata, current/next result rows, recursive queue trace, window rank shape, source-window token invalidation, stale cursor rejection, executor parity, unsupported SQL guards, and 52 generated rank-shift cases.
- Application smoke: `examples/application-compound-select-window-recursive-limit-current-source-next218.php`.
- Dependency closure: no new support component needed; the slice reuses native SELECT SQL compound execution, recursive queue LIMIT/OFFSET tracing, row_number window output, INTERSECT membership, and final LIMIT helpers.
- Non-overlap: avoids accepted next212 string-window EXCEPT fencing, next211 compound window/recursive LIMIT behavior, accepted SELECT subqueries, expression ORDER BY, JSON/WAL/B-tree/VFS clusters.
