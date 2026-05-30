# compound-select-window-recursive-limit-current-source-final-order-resume

Behavior slice: current-source compound SELECT handoff now records a final `ORDER BY` ordinal resume token for recursive/windowed `UNION` / `INTERSECT` / `EXCEPT` plans. Next-source rows are held until every current final-page ordinal acknowledgement matches the current token, which prevents a copied `wp_options` preview page from resuming against a shifted next-source page boundary.

Files:

- `src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `tests/SQLiteCompoundSelectWindowRecursiveLimitFinalOrderResumeTest.php`
- `examples/application-compound-select-window-recursive-limit-final-order-resume.php`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitFinalOrderResumeTest.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-final-order-resume.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitFinalOrderResumeTest.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-final-order-resume.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this composes existing compound SELECT execution, recursive LIMIT/OFFSET, avg/first_value window dispatch, current-source token fencing, and final LIMIT helpers.

Non-overlap: this is not suite final-order-resume evidence and does not touch JSON table, WAL/VFS, B-tree, encoding, planner range-cost, trigger, PRAGMA, accepted current-page-drain drain acknowledgements, or accepted union-intersect-except-window-limit avg/first_value compound-window behavior. The new behavior is the final-order ordinal acknowledgement contract layered over that accepted result.
