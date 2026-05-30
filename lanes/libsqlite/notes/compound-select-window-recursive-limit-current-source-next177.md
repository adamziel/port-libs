# compound-select-window-recursive-limit-current-source-next177

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE queue LIMIT rows and per-arm window ranks feed a final compound LIMIT at the current/next source boundary.

Behavior covered:

- `WITH RECURSIVE` queue `LIMIT` materializes the recursive arm before compound combination.
- `row_number()` and `dense_rank()` are evaluated in their own compound arms before final `ORDER BY` and `LIMIT`.
- The current copied `wp_options` source exactly fills the final compound limit.
- The next copied source adds a higher-ranked autoload row, changes table-arm dense ranks, and truncates the previous final recursive row after the compound LIMIT.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext177Test.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next177.php --self-test
```

Expected dashboard movement: focused `phpPass +63` from the new test file after acceptance (`255` assertions, `0` failures). `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, ORDER BY, and LIMIT inventory.

Non-overlap: avoids accepted compound zero-limit recursive/window next174, EXCEPT/intersect recursive/window next157/161/164/170/173, compound collation/affinity/window slices, parser-level SELECT GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is a positive final compound LIMIT that exactly admits the current source but truncates a next-source row after per-arm window rank changes.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSelectSql` recursive CTE tracing, compound `UNION ALL`, window row-array execution, ORDER BY, and final LIMIT machinery.
