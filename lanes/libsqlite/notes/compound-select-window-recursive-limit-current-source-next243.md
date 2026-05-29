# Compound SELECT Window Recursive LIMIT Current Source Next243

Status: focused PHP behavior growth for current-source compound SELECTs where final limited rows must replay their window metrics and recursive lineage before a changed next-source row can be admitted.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next240 spillover drain behavior. The new replay fence binds the current final page to row ordinal, row id, label, window metric, recursive emitted/skipped lineage, spillover token, and next-source labels. Stale replay tokens, stale signatures, missing tickets, and unexpected tickets reject next-source promotion.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next243.php` models copied `wp_options` rows where a new autoloaded plugin option crosses the final `UNION ALL` / `INTERSECT` / `EXCEPT` page while recursive seed rows keep their window metric lineage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next243.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Test.php`
  - Result: `1 test files, 453 assertions, 0 failures`, `83` PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next243.php --self-test`
  - Result: `wordpress-compound-select-window-recursive-limit-current-source-next243 self-test passed`.

Expected dashboard movement: `phpPass +83` from focused lane-local PASS lines. Mapped upstream coverage remains `647 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed. The slice reuses lane-local parser-level SELECT SQL, recursive CTE tracing, compound SELECT, window metric execution, spillover drain, and cursor-ticket machinery.

Non-overlap: avoids accepted next240 spillover-only acknowledgement, next238 source-generation seal, next226 aggregate EXCEPT/INTERSECT behavior, accepted SELECT SQL text/GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters. The narrower behavior is current-row replay-ticket admission after accepted spillover drain.
