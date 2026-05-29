# compound-select-window-recursive-limit-current-source-next233

Behavior slice: current-source compound SELECT handoff now records a final `ORDER BY` ordinal resume token for recursive/windowed `UNION` / `INTERSECT` / `EXCEPT` plans. Next-source rows are held until every current final-page ordinal acknowledgement matches the current token, which prevents a copied `wp_options` preview page from resuming against a shifted next-source page boundary.

Files:

- `src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext233Test.php`
- `examples/wordpress-compound-select-window-recursive-limit-current-source-next233.php`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext233Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next233.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext233Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next233.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this composes existing compound SELECT execution, recursive LIMIT/OFFSET, avg/first_value window dispatch, current-source token fencing, and final LIMIT helpers.

Non-overlap: this is not suite next233 evidence and does not touch JSON table, WAL/VFS, B-tree, encoding, planner range-cost, trigger, PRAGMA, accepted next228 drain acknowledgements, or accepted next230 avg/first_value compound-window behavior. The new behavior is the final-order ordinal acknowledgement contract layered over that accepted result.
