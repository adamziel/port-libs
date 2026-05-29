# Compound SELECT Window Recursive LIMIT Current Source Next165

Adds focused current-source next165 behavior for parser-level compound SELECTs
where named `WINDOW` clauses are expanded independently in each compound arm
before recursive CTE queue LIMIT/OFFSET rows and copied `wp_options` rows reach
the final compound ORDER BY/LIMIT/OFFSET boundary.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext165Test.php
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next165.php --self-test
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext165Test.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next165.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +67` from the new focused test file.
`benchmarkDenominator.mapped` remains `610 / 1589`; this is current-source PHP
behavior over already mapped recursive CTE, compound SELECT, named-window, and
LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive LIMIT next117, recursive
affinity/window next129, compound window frame LIMIT next131, compound
collation/window next136, compound LIMIT/window affinity next137, recursive
window LIMIT next156/158/160/162, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT
clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters,
and suite evidence handoffs. The narrower surface is named-window expansion in
each compound arm before the final compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local
parser-level SELECT SQL named-window expansion, recursive CTE queue, compound
combiner, window row-array execution, and result LIMIT/OFFSET machinery.
