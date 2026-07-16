# compound-select-window-recursive-limit-current-source-next139

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE queue LIMIT rows feed per-arm window evaluation and the final compound LIMIT/OFFSET decides the current/next Application row boundary.

Behavior covered:
- `WITH RECURSIVE` queue `LIMIT` exhausts before rows are exposed to the compound arm.
- Window functions in both compound arms are evaluated before `UNION ALL` row combination.
- Tail `ORDER BY window_weight DESC, id LIMIT 5 OFFSET 1` is applied after recursive and current-source rows are combined.
- A next-source copied `wp_options` autoload row moves across the final LIMIT boundary while recursive queue diagnostics remain stable.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveLimitCurrentSourceNext139Test.php
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-window-recursive-limit-current-source-next139.php --self-test
```

Expected dashboard movement: `phpPass +64` from the new focused test file. `benchmarkDenominator.mapped` remains `606 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive LIMIT next117, recursive affinity/window next129, compound window frame LIMIT next131, compound collation/window next136, compound LIMIT/window affinity next137, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is recursive queue LIMIT feeding per-arm window values before a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
