# compound-intersect-recursive-window-limit-current-source-next157

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE queue `LIMIT` feeds a window-ranked `INTERSECT` arm, and the final compound `LIMIT/OFFSET` decides the current/next Application row boundary.

This slice adds `SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan`. It records:

- recursive CTE queue rows and limit exhaustion before the compound operator;
- per-arm `row_number()` window metadata;
- `INTERSECT` retained and removed row traces;
- pre-limit rows, skipped offset rows, admitted rows, and truncated rows;
- current/next boundary changes for copied `wp_options` import rows.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNext157Test.php
php -l lanes/libsqlite/examples/application-compound-intersect-recursive-window-limit-current-source-next157.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNext157Test.php
php lanes/libsqlite/examples/application-compound-intersect-recursive-window-limit-current-source-next157.php --self-test
```

Focused result: `1 test files, 210 assertions, 0 failures`, with 64 PASS lines. The example printed `application-compound-intersect-recursive-window-limit-current-source-next157 self-test passed`.

Expected dashboard movement: `phpPass +64` from the new focused test file, from `69549` to `69613`. `benchmarkDenominator.mapped` remains `607 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive LIMIT next117, recursive affinity/window next129, compound recursive/window LIMIT next139, compound recursive/order/affinity next140/144/146/149, chained `EXCEPT` window LIMIT next141/143/148, compound LIMIT/window affinity next137, accepted compound row composition, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is recursive queue `LIMIT` plus window-ranked `INTERSECT` retention before final compound `LIMIT/OFFSET`.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue tracing, compound `INTERSECT`, window row-array execution, and result LIMIT/OFFSET machinery.
