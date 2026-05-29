# compound-select-window-recursive-limit-current-source-next211

Status: focused PHP behavior growth for `compound-select-window-recursive-limit-current-source-next211`.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, covering parser/executor current-source behavior for compound SELECT arms that include `sum(...) FILTER (...) OVER (...)` and `count(*) FILTER (...) OVER (...)`, a recursive CTE with `LIMIT/OFFSET`, `EXCEPT` removal of filtered-out rows, a `UNION` distinct tail, final `ORDER BY metric, id`, and tail `LIMIT/OFFSET`.

WordPress smoke: `wordpress-compound-select-window-recursive-limit-current-source-next211.php` models a copied `wp_options` preview where newly imported plugin/theme rows affect the pre-limit filtered window stream while the final compound limit still admits the stable recursive rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext211Test.php`
- Result: `1 test files, 390 assertions, 0 failures`, with `70` PASS lines.

Expected dashboard movement:

- `phpPass`: `+70` focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is current-source PHP behavior over existing SELECT/compound/window inventory, not a newly hydrated upstream manifest row.

Non-overlap:

Avoids accepted next209 unfiltered `sum`/`count` aggregate windows, next206 `lead`/`nth_value` INTERSECT fencing, next203 `lag`/`last_value` EXCEPT fencing, next196 `ntile`/`first_value` UNION distinct behavior, next188 endpoint windows, accepted JSON/WAL/B-tree/VFS clusters, and the current next115/next116 live pool surfaces. The new behavior is specifically FILTERed aggregate window output flowing through recursive, EXCEPT, UNION distinct, and final compound LIMIT current-source fences.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteSelectSql` compound execution, recursive CTE tracing, FILTERed aggregate window parsing/evaluation, EXCEPT/UNION membership, and final LIMIT helpers.
