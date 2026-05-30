# compound-select-window-recursive-limit-current-source-next185

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE queue emits exactly one row after `LIMIT 1 OFFSET n`, then a `UNION` distinct arm collapses duplicate windowed rows before a `UNION ALL` tail and final `LIMIT/OFFSET` current/next boundary.

Behavior covered:

- recursive queue offset skips the anchor and intermediate rows before the single emitted row;
- `row_number()`, `dense_rank()`, and `rank()` window values are materialized inside their compound arms before set operations;
- `UNION` distinct duplicate collapse happens before the final `UNION ALL` tail preserves duplicate Application option rows;
- final compound `ORDER BY metric, id LIMIT 5 OFFSET 1` decides the current/next boundary after the current source gains a plugin option.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext185Test.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next185.php --self-test
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext185Test.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next185.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: focused `phpPass +63` from the new test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted compound recursive/window LIMIT next139/158/159/160/166/168/170/172/175/177/178/182, EXCEPT/INTERSECT variants, LIMIT-zero exhaustion, comma-LIMIT, lag/lead variants, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower next185 surface is `UNION` distinct duplicate collapse between a single-row recursive LIMIT/OFFSET window arm and a duplicate-preserving `UNION ALL` tail.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, `UNION` distinct, `UNION ALL`, and result LIMIT/OFFSET machinery.
