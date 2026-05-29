# Compound SELECT Window Recursive LIMIT Current Source Next251

Status: focused PHP behavior growth for current-source compound SELECTs where a next-source promotion must also prove the compound operator trace and final page row ordinals before the next source can be admitted.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next248 next-source promotion receipts. The new fence binds promotion to the `UNION ALL` / `INTERSECT` / `EXCEPT` operator sequence, final current/next page ordinals, labels, ids, metrics, recursive lineage, and next248 promotion token. Stale audit tokens, stale signatures, missing receipts, unexpected receipts, and non-list receipts reject admission.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next251.php` models copied `wp_options` rows where a new autoloaded plugin option changes the final compound page. The next source remains held until the operator/final-page audit matches the replayed current result and next delta.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Test.php`
  - Result: `1 test files, 454 assertions, 0 failures` with `85` PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next251.php --self-test`
  - Result: `wordpress-compound-select-window-recursive-limit-current-source-next251 self-test passed`.

Expected dashboard movement: `phpPass +85` from focused lane-local PASS lines. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed; this reuses lane-local SELECT SQL compound execution, recursive CTE queue tracing, window metric rows, next243 replay tickets, next248 promotion receipts, and existing cursor receipt validation.

Non-overlap: next251 layers an operator/final-page ordinal audit over accepted next248 next-source promotion receipts. It avoids accepted next243 replay-ticket admission, next248 promotion-only receipts, accepted compound/window/recursive LIMIT next245-next248 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters.
