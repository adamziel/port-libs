# compound-select-window-recursive-limit-current-source-next156

Status: focused PHP behavior growth for a parser-level compound SELECT whose recursive CTE arm exhausts its queue `LIMIT`, whose arms compute window values before combination, and whose final compound `LIMIT/OFFSET` changes when a next copied `wp_options` source appears.

Behavior covered:

- `WITH RECURSIVE` queue `LIMIT` is exhausted before rows enter the compound SELECT arm.
- `row_number()` over recursive rows and `lag()` over copied `wp_options` rows are evaluated per arm before `UNION ALL`.
- Final `ORDER BY win_value DESC, id LIMIT 6 OFFSET 2` is applied after compound row combination.
- A next-source `plugin_alpha` row shifts the admitted Application/current boundary without changing the recursive queue trace.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext156Test.php
php -l lanes/libsqlite/examples/application-compound-window-recursive-limit-current-source-next156.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext156Test.php
php lanes/libsqlite/examples/application-compound-window-recursive-limit-current-source-next156.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +65` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted compound recursive LIMIT next117, recursive affinity/window next129 and next142, compound collation/window next136, compound LIMIT/window affinity next137, recursive affinity order next140, compound EXCEPT/window LIMIT next148, recursive affinity LIMIT next149, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The new surface is the current/next Application boundary after recursive queue LIMIT exhaustion and per-arm `row_number()`/`lag()` window evaluation before the final compound LIMIT/OFFSET.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
