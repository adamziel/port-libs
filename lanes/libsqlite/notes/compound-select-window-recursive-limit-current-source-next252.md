# Compound SELECT Window Recursive LIMIT Current Source Next252

Status: focused PHP behavior growth for current-source compound SELECTs whose final limited page must be yield-acknowledged before a next source can publish rows.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Plan`, layered on accepted next249 promotion epochs. The new final-page yield watermark binds current and next limited rows to the promotion epoch, recursive lineage token, window metric token, and current-only/next-only label delta before a copied WordPress `wp_options` row can cross the final compound page.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next252.php` models copied autoloaded options where `plugin_prime` enters the final `UNION ALL` / `INTERSECT` / `EXCEPT` page only after the current page token, next page token, recursive lineage token, and window metric token are acknowledged together.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next252.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next252.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +80` from focused lane-local PASS lines. Mapped upstream coverage remains `663 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed. The slice reuses lane-local parser-level SELECT SQL, recursive CTE tracing, compound SELECT, window metric execution, spillover drains, replay tickets, promotion snapshots, and next249 promotion epochs.

Non-overlap: avoids next248 receipt-only promotion, next249 epoch-only admission, accepted batch217 next249 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters. The narrower behavior is the final-page current/next yield watermark after next249 recursive-lineage/window-metric promotion epochs.
