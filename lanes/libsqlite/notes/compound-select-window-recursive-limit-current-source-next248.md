# Compound SELECT Window Recursive LIMIT Current Source Next248

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next243 current replay-ticket fencing. The new behavior gates next-source promotion for compound SELECTs that combine recursive CTE LIMIT/OFFSET, window metrics, `UNION ALL` / `INTERSECT` / `EXCEPT`, and final LIMIT/OFFSET. Promotion now requires receipts for the next-source delta set, binding next-only/current-only labels to the current replay token, spillover-drain token, recursive lineage, and next window metric frames.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next248.php` models copied `wp_options` rows where a newly autoloaded plugin option displaces a current option on the final compound page. The next source remains held until promotion receipts match the replayed current result and next delta.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext248Test.php`
  - Result: `1 test files, 455 assertions, 0 failures`
  - PASS lines: `84`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next248.php --self-test`
  - Result: `wordpress-compound-select-window-recursive-limit-current-source-next248 self-test passed`

Dependency closure: no new support component needed; this reuses lane-local SELECT SQL compound execution, recursive CTE queue tracing, window metric rows, next243 replay tickets, and next240 spillover drains.

Non-overlap: next248 extends accepted next243 current-row replay tickets with a next-source promotion receipt keyed to the next result delta. It avoids accepted next245 compound/window/recursive LIMIT behavior, next246/next247 storage/window handoffs, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters.
