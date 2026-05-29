# compound-select-window-recursive-limit-current-source-next169

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE queue uses `ORDER BY` with comma-form `LIMIT offset,count`, each compound arm evaluates `ntile()` before row combination, and the final compound `LIMIT/OFFSET` moves the WordPress current/next boundary.

Behavior covered:

- `WITH RECURSIVE` queue `ORDER BY 1 LIMIT 1, 5` skips the anchor row and emits the bounded recursive queue in SQLite comma-limit order.
- `ntile(2)` window values are evaluated independently in the recursive arm and the copied `wp_options` arm before `UNION ALL`.
- Tail `ORDER BY bucket, id LIMIT 6 OFFSET 1` is applied after recursive and table rows are combined.
- A next-source `rewrite_rules` row changes the first bucket and shifts a recursive row across the final LIMIT boundary.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext169Test.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next169.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext169Test.php
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next169.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +69` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next139/next156/next158 final-limit variants, next157 INTERSECT, next161/next162 EXCEPT plus `lead()`, next165 named-window `dense_rank()`/`row_number()`, SELECT SQL comma LIMIT standalone behavior, grouped/JOIN/subquery/ORDER-expression SQL text clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and suite evidence handoffs. The narrower surface is recursive queue `ORDER BY` plus comma-form queue LIMIT feeding `ntile()` window buckets before a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ORDER/LIMIT parsing, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
