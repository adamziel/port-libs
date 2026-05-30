# compound-select-window-recursive-limit-current-source-next205

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source token fence for compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `rank()` peer output and `dense_rank()` partition output evaluated inside compound arms before `INTERSECT` membership;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows change the post-window compound boundary. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, compound, window, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext205Test.php` -> `1 test files, 332 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next205.php` -> emitted `compound-select-window-recursive-limit-current-source-next205-ready` with `rank` / `dense_rank` window functions and 64-byte current/next source tokens
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext205Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next205.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +60` from the new focused test file. `benchmarkDenominator.mapped` remains `620 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next203 `lag()`/`last_value()` `EXCEPT` fencing, next196 `ntile()`/`first_value()` `UNION` distinct fencing, next195 `INTERSECT`/`EXCEPT` row_number membership, next192 `percent_rank()`/`cume_dist()` distribution-window fencing, accepted JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE sorter/window collation work, and encoding-only LIKE/GLOB/collation clusters. The narrower surface is ordered recursive queue LIMIT feeding `rank()` peer groups plus `dense_rank()` partition output before an `INTERSECT` tail and post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
