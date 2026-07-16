# compound-select-window-recursive-limit-current-source-next219

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE queue `LIMIT/OFFSET` feeds `percent_rank()` and `cume_dist()` window arms before an `EXCEPT` membership fence and final compound `LIMIT/OFFSET` decide the current/next Application row boundary.

Behavior covered:
- `WITH RECURSIVE` queue `ORDER BY ... LIMIT ... OFFSET` is traced before compound arm output.
- `percent_rank()` rows from the recursive arm and `cume_dist()` rows from `wp_options` are evaluated before compound membership.
- `EXCEPT` is applied after window output, so stale plugin rows are fenced before the final limited preview.
- Current-source cursor tokens reject stale continuation, while next-source copied plugin rows move across the final LIMIT boundary.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext219Test.php
1 test files, 357 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next219.php
```

Expected dashboard movement: `phpPass +65` from the new focused PASS lines. `benchmarkDenominator.mapped` remains `624 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next217 rank/dense_rank INTERSECT fencing, next213 min/max INTERSECT fencing, next212 group_concat/row_number EXCEPT fencing, next210 row_number/last_value INTERSECT+EXCEPT fencing, accepted SELECT SQL JOIN/GROUP/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is percent_rank/cume_dist window output fenced through EXCEPT before a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, current-source token, and result LIMIT/OFFSET machinery.
