# compound-select-window-recursive-limit-current-source-next168

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE comma-form `LIMIT offset,count` rows feed per-arm window evaluation and a final compound comma-form LIMIT selects the current/next WordPress boundary.

Behavior covered:

- Recursive CTE queue `LIMIT 1,5` skips the anchor row and emits the next five recursive rows.
- Window functions in both compound arms are evaluated before `UNION ALL` row combination.
- Tail `ORDER BY metric DESC, id LIMIT 1,4` is applied after recursive and copied `wp_options` rows are combined.
- A next-source copied `wp_options` autoload row moves into the final LIMIT slice while the previous recursive tail row is pushed out.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext168Test.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next168.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext168Test.php
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next168.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +66` from the new focused test file. `benchmarkDenominator.mapped` remains `611 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next139/next158 recursive final LIMIT/OFFSET coverage, next162 UNION ALL plus EXCEPT window coverage, next164 INTERSECT/window recursive LIMIT coverage, next163 multi-anchor recursion, SELECT SQL comma LIMIT standalone coverage, SELECT SQL GROUP/JOIN/subquery/ORDER clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is comma-form LIMIT at both the recursive queue and final compound SELECT boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and comma-form LIMIT/OFFSET machinery.
