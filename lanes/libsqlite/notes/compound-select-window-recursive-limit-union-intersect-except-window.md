# compound-select-window-recursive-limit-current-source-union-intersect-except-window-limit

Status: focused PHP behavior growth for parser-level compound SELECT output where
recursive CTE queue `ORDER BY ... LIMIT ... OFFSET` rows feed `avg()` aggregate
window output and `first_value()` frame output before `UNION` distinct,
`INTERSECT`, `EXCEPT`, and final compound `LIMIT/OFFSET`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionIntersectExceptWindowLimitTest.php`
- Result: `1 test files, 343 assertions, 0 failures` with 62 PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-intersect-except-window.php`
- Result: JSON self-test payload emitted with `plugin_prime` in `nextOnlyRows`.

Implementation note: `avg()` is now admitted through the existing native
`SQLiteSelectSql` window aggregate path and `SQLiteWindowFunction` frame
aggregator, matching the existing `count`, `sum`, `min`, `max`, and
`group_concat` window-frame dispatch.

Expected dashboard movement: `phpPass +62` from the new focused test file.
Mapped coverage remains unchanged because this is current-source PHP behavior
over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed; this reuses lane-local
parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window
row-array execution, set-membership, and result LIMIT/OFFSET machinery.

Non-overlap: avoids accepted next226 sum/count EXCEPT+INTERSECT fencing, next225
lag/last_value INTERSECT+EXCEPT fencing, next219 percent_rank/cume_dist EXCEPT
fencing, next217 rank/dense_rank INTERSECT fencing, next213 min/max INTERSECT
fencing, next212 group_concat/row_number EXCEPT fencing, accepted JSON table
source/cursor/constraint work, VFS/WAL/B-tree clusters, encoding LIKE/GLOB/
collation work, and suite-runner evidence. The narrower surface is `avg()`
window output plus `first_value()` frame output through `UNION` distinct,
`INTERSECT`, and `EXCEPT` before final compound LIMIT over current and next
`wp_options` sources.
