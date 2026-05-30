# Compound SELECT Window Recursive LIMIT Current Source Next254

Status: focused PHP behavior growth for current-source compound SELECTs whose
next-source admission must be acknowledged against the compound operator chain,
recursive LIMIT lineage, final LIMIT/OFFSET position, and per-page window
frames.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`,
layered on accepted next250 next-page admission. The new receipt gate binds the
accepted next-page token to `UNION ALL` / `INTERSECT` / `EXCEPT`, recursive
emitted/skipped labels, current/next window page frames, and final
`LIMIT 4 OFFSET 1` before a copied Application `wp_options` next source can
publish rows across the current boundary.

Application path: `application-compound-select-window-recursive-limit-current-source-next254.php`
models copied autoloaded `wp_options` rows where `plugin_prime` enters the next
compound page. The current page remains held until the current rows,
recursive queue, window metrics, and compound receipt all agree.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Test.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next254.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next254.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +88` from focused lane-local PASS lines.
Mapped upstream coverage remains `669 / 1589`; this is current-source PHP
behavior over already mapped compound SELECT, recursive CTE, window, and LIMIT
inventory.

Dependency closure: no new support component is needed. The slice reuses
lane-local parser-level SELECT SQL, recursive CTE tracing, compound SELECT,
window frame execution, and accepted next250 next-page admission machinery.

Non-overlap: avoids accepted next248 promotion receipts, next249 epoch fences,
next250 next-page admission alone, batch218 next250 compound/window/recursive
LIMIT behavior, row-value/window RETURNING, trigger recursive UPSERT, JSON
table, WAL/VFS, B-tree, planner, PRAGMA, encoding, VDBE, and suite evidence
clusters. The narrower behavior is the post-admission compound/window/recursive
receipt gate for next254 current-source publication.
