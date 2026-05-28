# Compound SELECT Window Recursive LIMIT Current Source Next257

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext257Plan`, layered on the existing next252 final-page yield watermark. The new behavior models the source-switch checkpoint needed before a next-source rowset can replace the current-source final page for compound SELECTs that combine recursive CTE LIMIT/OFFSET, window metrics, `UNION ALL` / `INTERSECT` / `EXCEPT`, and final LIMIT/OFFSET.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next257.php` models copied `wp_options` preview rows where a newly autoloaded plugin option displaces the current `rewrite_rules` row on the limited compound page. The source switch is held until ordered current-page, next-page, and delta-label receipts match the recursive/window checkpoint.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext257Test.php`
  - Result: `1 test files, 612 assertions, 0 failures`
  - PASS lines: `86`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next257.php --self-test`
  - Result: `wordpress-compound-select-window-recursive-limit-current-source-next257 self-test passed`

Dependency closure: no new support component needed; this reuses lane-local SELECT SQL compound execution, recursive CTE queue tracing, window metric rows, next249 promotion epochs, and next252 final-page watermarks.

Non-overlap: next257 extends accepted next252 final-page yield watermarks with ordered current-to-next source-switch receipts. It avoids accepted batch219 next251/next252 compound/window/recursive LIMIT behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters.
