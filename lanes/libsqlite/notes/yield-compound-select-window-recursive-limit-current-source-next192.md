# compound-select-window-recursive-limit-current-source-next192

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source token fence for compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `percent_rank()` and `cume_dist()` window output evaluated before compound de-duplication/final ordering;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows change distribution-window rank boundaries or recursive queue fronts. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, compound, window, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext192Test.php` -> `1 test files, 417 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next192.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext192Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next192.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next189 row_number/dense_rank token fence, next188 endpoint windows, next186 rank/dense_rank comma LIMIT behavior, JSON table, VFS/WAL, B-tree, trigger, PRAGMA, and encoding clusters.
