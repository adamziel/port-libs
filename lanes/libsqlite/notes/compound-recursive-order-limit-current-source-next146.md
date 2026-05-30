# compound-select-recursive-order-limit-current-source-next146

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE priority queue uses `ORDER BY priority DESC, name LIMIT 5` before a final compound `ORDER BY priority DESC, name LIMIT 6 OFFSET 1` chooses the current/next Application option boundary.

Behavior covered:

- recursive queue ordering is applied after each generated wave and before queue-limit exhaustion;
- the queue `LIMIT` stops recursive row emission before the outer compound arm reads copied `wp_options` rows;
- compound row names come from the left recursive arm while the current-source arm is renamed by position;
- the final compound `ORDER BY`/`LIMIT` runs after recursive and current-source rows are combined;
- a next-source high-priority plugin row changes both recursive visit order and the final LIMIT boundary.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundRecursiveOrderLimitCurrentSourceNext146Test.php
php -l lanes/libsqlite/examples/application-compound-recursive-order-limit-current-source-next146.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveOrderLimitCurrentSourceNext146Test.php
php lanes/libsqlite/examples/application-compound-recursive-order-limit-current-source-next146.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +67` from the new focused test file. `benchmarkDenominator.mapped` remains `606 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, ORDER BY, and LIMIT inventory rather than a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive LIMIT next117, compound recursive collation/limit next132, compound recursive window/limit next139, compound recursive affinity/window next142, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is recursive queue priority `ORDER BY` plus queue `LIMIT` feeding the post-compound final `ORDER BY`/`LIMIT` current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, and final result ORDER/LIMIT machinery.
