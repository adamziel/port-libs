# Compound SELECT Window Recursive LIMIT Current Source WindowMetricFence

Status: focused PHP behavior growth for compound SELECTs where recursive CTE rows and copied `wp_options` rows both produce window metrics before `UNION` / `INTERSECT` / `EXCEPT`, final `ORDER BY`, and final `LIMIT/OFFSET` page selection.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on the accepted final-order-resume final-ordinal resume fence. The new behavior requires per-row current-source window metric acknowledgements before next-source rows are exposed. This catches the narrower upstream-style hazard where labels or ordinals can still look resumable while `sum()` / `nth_value()` window metrics drift after next-source `wp_options` rows are staged.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitWindowMetricFenceTest.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-window-metric-fence.php`

Expected dashboard movement: `phpPass +71` from the new focused test file. `benchmarkDenominator.mapped` remains `640 / 1589`; this is current-source PHP behavior over already mapped compound SELECT, recursive CTE, window, and LIMIT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed; the patch reuses lane-local parser-level SELECT SQL, recursive CTE queue handling, compound set operators, window execution, final ordering, and LIMIT/OFFSET machinery.

Non-overlap: avoids accepted next226/current-page-drain/union-intersect-except-window-limit/final-order-resume compound recursive/window LIMIT variants, batch205 suite236 evidence, JSON table rowid/cost repair, WAL/VFS, B-tree, planner, trigger, PRAGMA, and encoding clusters. The new surface is specifically a current-source window-metric acknowledgement fence after final-order ordinal fencing.
