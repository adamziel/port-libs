# compound-select-window-recursive-limit-current-source-next203

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source token fence for compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `lag()` default output and `last_value()` frame output evaluated inside compound arms before `EXCEPT` membership;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows change the post-window compound boundary. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, compound, window, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext203Test.php` -> `1 test files, 379 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next203.php` -> emitted `compound-select-window-recursive-limit-current-source-next203-ready` with `lag` / `last_value` window functions and 64-byte current/next source tokens
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext203Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next203.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68` from the new focused test file. `benchmarkDenominator.mapped` remains `619 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next196 `ntile()`/`first_value()` `UNION` distinct fencing, next195 `INTERSECT`/`EXCEPT` row_number membership, next192 `percent_rank()`/`cume_dist()` distribution-window fencing, next191 `nth_value()`/`ntile()`/`lead()` value-offset tape, accepted JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE sorter/window collation work, and encoding-only LIKE/GLOB/collation clusters. The narrower surface is ordered recursive queue LIMIT feeding `lag()` defaults plus `last_value()` frame output before an `EXCEPT` tail and post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
