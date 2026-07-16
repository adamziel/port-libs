# compound-window-recursive-yield-current-source-next159

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE queue `LIMIT` is exhausted before per-arm `ntile()` / `percent_rank()` window evaluation and the final compound comma-form `LIMIT offset,count` yields current/next boundary slots.

Behavior covered:

- `WITH RECURSIVE` queue `LIMIT` is consumed before rows are exposed to the windowed compound arm.
- `ntile()` and `percent_rank()` are evaluated inside their individual compound arms before `UNION ALL` row combination.
- Final compound `ORDER BY win_value DESC, id LIMIT 2, 6` is applied after windowed recursive and `wp_options` rows are combined.
- Current/next diagnostics retain pre-LIMIT yield slot indexes and classify yielded rows as recursive vs table source rows.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveYieldCurrentSourceNext159Test.php
php lanes/libsqlite/examples/application-compound-window-recursive-yield-current-source-next159.php --self-test
```

Expected dashboard movement: `phpPass +63` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive/window LIMIT next139 and next156 row-number/lag surfaces, compound recursive affinity/window next142, compound recursive/order/limit next146, compound recursive affinity limit next149, EXCEPT/window LIMIT next148, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is final comma-LIMIT yield-slot diagnostics after recursive queue exhaustion and `ntile()` / `percent_rank()` per-arm window values.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and comma LIMIT/OFFSET machinery.
