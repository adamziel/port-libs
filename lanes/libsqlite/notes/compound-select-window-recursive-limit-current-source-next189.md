# compound-select-window-recursive-limit-current-source-next189

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source token fence for compound SELECTs that combine:

- `WITH RECURSIVE` queue `LIMIT/OFFSET`;
- window output evaluated before compound de-duplication/final ordering;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows change the recursive/windowed compound boundary. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, window, compound, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext189Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next189.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext189Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next189.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next186 comma-LIMIT rank/dense-rank behavior, next147 cursor paging, JSON table, VFS/WAL, B-tree, trigger, PRAGMA, and encoding clusters.
