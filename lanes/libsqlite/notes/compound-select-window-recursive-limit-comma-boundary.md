# compound-select-window-recursive-limit-comma-boundary

Status: migrated from generated worker-numbered entry/helper names to the stable comma-boundary compound SELECT entry point. The covered parser-level behavior is unchanged: recursive CTE comma-form `LIMIT offset,count` rows feed per-arm window evaluation and a final compound comma-form LIMIT selects the current/next Application boundary.

Behavior covered:

- Recursive CTE queue `LIMIT 1,5` skips the anchor row and emits the next five recursive rows.
- Window functions in both compound arms are evaluated before `UNION ALL` row combination.
- Tail `ORDER BY metric DESC, id LIMIT 1,4` is applied after recursive and copied `wp_options` rows are combined.
- A next-source copied `wp_options` autoload row moves into the final LIMIT slice while the previous recursive tail row is pushed out.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-comma-boundary.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-comma-boundary.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: no `phpPass` or mapped coverage change; this is a method/test/example consolidation over existing current-source PHP behavior.

Non-overlap: avoids accepted recursive final LIMIT/OFFSET coverage, UNION ALL plus EXCEPT window coverage, INTERSECT/window recursive LIMIT coverage, multi-anchor recursion, SELECT SQL comma LIMIT standalone coverage, SELECT SQL GROUP/JOIN/subquery/ORDER clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is comma-form LIMIT at both the recursive queue and final compound SELECT boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and comma-form LIMIT/OFFSET machinery.
