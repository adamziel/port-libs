# compound-select-window-recursive-limit-current-source-next258

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan`, a bounded current-source high-water handoff for compound SELECTs that combine recursive CTE LIMIT/OFFSET, window output, `UNION ALL` / `INTERSECT` / `EXCEPT`, and final compound `ORDER BY ... LIMIT ... OFFSET`.

The new behavior extends accepted next254 receipt gating: after compound/window/recursive receipts are known, next-source rows are still held until the current page's final admitted row and recursive queue digest are acknowledged. This prevents a stale current-source cursor from resuming across the final compound LIMIT boundary and exposing next-source plugin option rows early.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next258.php` models copied `wp_options` preview queries where `plugin_prime` stays a next-source candidate until the current-source high-water row `seed:2:3:4` is acknowledged.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Test.php`
  - `1 test files, 500 assertions, 0 failures`
  - `91` focused PASS lines
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next258.php`
  - `No syntax errors detected`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next258.php`
  - emitted `compound-select-window-recursive-limit-current-source-next258-ready` with high-water `seed:2:3:4`, next candidate `plugin_prime`, and `4` required acknowledgements
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard movement: `phpPass +91` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive queue tracing, compound SELECT execution, window result shaping, final LIMIT/OFFSET, and current-source cursor token machinery.

Non-overlap: avoids accepted next253/next254 compound/window/recursive LIMIT receipt behavior and accepted row-value/window, trigger, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, VDBE, and suite evidence clusters. The narrower surface is the final current-page high-water token and recursive digest gate before exposing next-source rows.
