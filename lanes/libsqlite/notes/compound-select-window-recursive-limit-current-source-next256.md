# Compound SELECT Window Recursive LIMIT Current Source Next256

Status: focused PHP behavior growth for current-source compound SELECTs where a final `LIMIT/OFFSET` page must prove its window metrics and recursive queue exhaustion before next-source promotion receipts can publish.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next248 promotion receipts. The new fence binds the current final page to row ordinal, row id, label, window metric, recursive emitted/skipped lineage, recursive LIMIT/OFFSET exhaustion, next248 promotion token, and next delta signature.

Application path: `application-compound-select-window-recursive-limit-current-source-next256.php` models copied `wp_options` rows where a next-source plugin option changes the final `UNION ALL` / `INTERSECT` / `EXCEPT` page. The current page remains held until every current LIMIT row and the recursive queue exhaustion receipt are acknowledged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next256.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext256Test.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next256.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +77` from focused lane-local PASS lines. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed. The slice reuses lane-local parser-level SELECT SQL, recursive CTE tracing, compound SELECT, window metric execution, next248 promotion receipts, and result LIMIT/OFFSET machinery.

Non-overlap: avoids accepted next248 promotion receipts by adding the separate current final-page resume receipt required before promotion can proceed. It also avoids accepted next251/next252 compound/window/recursive LIMIT handoffs, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters.
