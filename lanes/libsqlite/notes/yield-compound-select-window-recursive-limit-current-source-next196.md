# compound-select-window-recursive-limit-current-source-next196

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source token fence for compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `ntile()` bucket output and `first_value()` frame output evaluated inside compound arms before `UNION` distinct handling;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows change the post-window compound boundary. It does not add a new support dependency; it reuses lane-local SELECT SQL, recursive CTE, compound, window, and row materialization helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext196Test.php` -> `1 test files, 379 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next196.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext196Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next196.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68` from the new focused test file. `benchmarkDenominator.mapped` remains `618 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next192 `percent_rank()`/`cume_dist()` distribution-window fencing, next191 `nth_value()`/`ntile()`/`lead()` value-offset tape, next190 expression LIMIT windows, accepted JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE sorter/window collation work, and encoding-only LIKE/GLOB/collation clusters. The narrower surface is ordered recursive queue LIMIT feeding `ntile()` plus `first_value()` frame output before a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
