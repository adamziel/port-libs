# compound-select-window-recursive-limit-current-source-next202

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source cursor replay fence for parser-level compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `ntile()` bucket output and `first_value()` frame output evaluated inside compound arms before `UNION` distinct handling;
- final compound `ORDER BY ... LIMIT ... OFFSET ...`;
- current/next copied `wp_options` source comparisons.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext202Test.php` -> `1 test files, 379 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next202.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext202Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next202.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68` from the new focused test file. `benchmarkDenominator.mapped` remains `619 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next196 by narrowing this handoff to cursor replay validation and stale-token rejection for current-source compound results; it also avoids accepted distribution/value-offset/expression LIMIT windows, JSON/VFS/WAL/B-tree/planner/PRAGMA/trigger/encoding clusters, and suite evidence handoffs.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, final LIMIT/OFFSET, and current-source cursor validation helpers.
