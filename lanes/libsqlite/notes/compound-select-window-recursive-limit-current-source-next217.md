# compound-select-window-recursive-limit-current-source-next217

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source fence for compound SELECTs that combine:

- `WITH RECURSIVE` queue `ORDER BY ... LIMIT ... OFFSET`;
- `rank()` and `dense_rank()` window output before compound membership;
- `UNION ALL` followed by `INTERSECT`;
- final compound `ORDER BY ... LIMIT ... OFFSET`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows alter the pre-limit windowed compound membership. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, window, compound, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext217Test.php`
- Result: `1 test files, 344 assertions, 0 failures` with 63 PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next217.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next217.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +63` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next212 group_concat/row_number EXCEPT fencing, next210 row_number/last_value INTERSECT+EXCEPT fencing, next209 sum/count aggregate windows, next206 lead/nth_value INTERSECT fencing, and JSON/WAL/B-tree/VFS/PRAGMA/trigger/planner/encoding clusters. The narrower surface is rank/dense_rank window output through INTERSECT before the final compound LIMIT over current and next copied `wp_options` sources.

Dependency closure: no new support component is needed; this reuses native SELECT SQL compound execution, recursive queue ORDER BY/LIMIT/OFFSET, rank/dense_rank window dispatch, INTERSECT membership, current-source tokens, and final LIMIT helpers.
