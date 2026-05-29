# compound-select-window-recursive-limit-current-source-next225

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a bounded current-source fence for compound SELECTs that combine:

- recursive CTE queue `ORDER BY ... LIMIT ... OFFSET ...`;
- `lag(..., default)` output and `last_value()` frame output evaluated before compound membership;
- `UNION ALL`, `INTERSECT`, and `EXCEPT` compound stages;
- final compound `ORDER BY ... LIMIT ... OFFSET` admission over copied `wp_options` rows.

The behavior prevents a stale current-source cursor from being reused after staged next-source rows move across the post-window compound LIMIT boundary.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext225Test.php`
- Result: `1 test files, 393 assertions, 0 failures` with 70 PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next225.php`
- Result: JSON self-test payload emitted with `plugin_prime` in `nextOnlyRows`.

Expected dashboard movement: `phpPass +70` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, set-membership, and result LIMIT/OFFSET machinery.

Non-overlap: avoids accepted next219 percent_rank/cume_dist EXCEPT fencing, next212 group_concat/row_number EXCEPT fencing, next210 row_number/last_value INTERSECT+EXCEPT fencing, next206 lead/nth_value INTERSECT fencing, accepted JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, encoding LIKE/GLOB/collation work, and suite-runner evidence. The narrower surface is lag-default plus last_value frame output through an INTERSECT stage followed by EXCEPT before final compound LIMIT over current and next `wp_options` sources.
