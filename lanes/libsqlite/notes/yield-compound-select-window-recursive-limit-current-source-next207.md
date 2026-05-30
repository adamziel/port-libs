# compound-select-window-recursive-limit-current-source-next207

Status delta: adds current-source focused coverage for a recursive CTE feeding a compound SELECT whose `lead()` and `nth_value()` window outputs are materialized before `UNION ALL`, whose first recursive window row is removed by an `EXCEPT` fence, and whose surviving rows are checked through an `INTERSECT` membership arm before final `ORDER BY ... LIMIT ... OFFSET` admission.

Application smoke: `examples/application-compound-select-window-recursive-limit-current-source-next207.php` models copied `wp_options` preview rows where a stale recursive dependency row is fenced before staged next-source autoload rows shift the final current/next LIMIT boundary.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext207Test.php`
  - `1 test files, 451 assertions, 0 failures`
  - 70 focused PASS lines.

Expected dashboard movement: `phpPass +70` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, EXCEPT/INTERSECT, and LIMIT inventory rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted next206 lead/nth_value INTERSECT-only fencing, next203 lag/last_value EXCEPT fencing, next196 ntile/first_value UNION distinct, next195 INTERSECT/EXCEPT row_number membership, next192 percent_rank/cume_dist distribution windows, accepted SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and suite evidence handoffs. The narrower next207 surface is EXCEPT removal of the first recursive `lead()` row before INTERSECT membership and final compound LIMIT/OFFSET current-source cursor fencing.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue tracing, compound combiner, window row-array execution, EXCEPT, INTERSECT, current-source tokens, and final LIMIT/OFFSET machinery.
