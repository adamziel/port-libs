# compound-select-window-recursive-limit-current-source-next212

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source fence for compound SELECTs that combine:

- `WITH RECURSIVE` queue `ORDER BY ... LIMIT ... OFFSET`;
- `group_concat()` string window output and `row_number()` window output before compound membership;
- `UNION ALL` followed by `EXCEPT`;
- final compound `ORDER BY ... LIMIT ... OFFSET`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows alter pre-limit windowed compound membership. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, window, compound, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext212Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next212.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next212.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next209 sum/count aggregate window EXCEPT+UNION fencing, next206 lead/nth_value INTERSECT fencing, next203 lag/last_value EXCEPT fencing, JSON table, WAL/VFS, B-tree, PRAGMA, trigger, row-value, planner, and encoding clusters.
