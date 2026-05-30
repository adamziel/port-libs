# compound-select-window-recursive-limit-current-source-next172

Status: focused PHP behavior growth for parser-level compound SELECT execution where a recursive CTE queue LIMIT is exhausted before per-arm `lead()` / `cume_dist()` window evaluation, then DISTINCT `UNION` and final `LIMIT/OFFSET` decide the copied Application current/next boundary.

Behavior covered:
- `WITH RECURSIVE` queue LIMIT exhausts before rows enter the compound arm.
- Window values are evaluated per arm before DISTINCT `UNION` duplicate handling.
- Final `ORDER BY window_rank DESC, id LIMIT 6 OFFSET 2` is applied after compound de-duplication.
- A next-source copied `wp_options` autoload row changes window outputs while row labels at the final boundary remain stable.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext172Test.php
```

Result: `1 test files / 256 assertions / 0 failures / 65 PASS lines`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next172.php --self-test
```

Result: `application-compound-select-window-recursive-limit-current-source-next172 self-test passed`.

Expected dashboard movement: `phpPass +65` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive/window LIMIT next139/next156/next158/next160/next162/next165/next166, recursive affinity/window, compound EXCEPT/INTERSECT, SELECT SQL JOIN/GROUP/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is DISTINCT `UNION` after per-arm `lead()`/`cume_dist()` values with recursive queue exhaustion and a current/next final LIMIT boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE tracing, compound DISTINCT UNION, window execution, and result LIMIT/OFFSET machinery.
