# Compound SELECT Window Recursive LIMIT Current Source Next249

Status: focused PHP behavior growth for current-source compound SELECTs whose acknowledged next-source promotion must also match recursive lineage and per-arm window metrics.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Plan`, layered on accepted next245 promotion snapshots. The new promotion epoch binds the next-source delta to recursive emitted/skipped/truncated labels and current/next window metric vectors before a copied WordPress `wp_options` row can cross the final compound LIMIT boundary.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next249.php` models copied `wp_options` autoload rows where `plugin_prime` enters the final `UNION ALL` / `INTERSECT` / `EXCEPT` page, but only after the current replay tickets, next245 promotion tickets, recursive lineage token, and window metric token agree.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next249.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +87` from focused lane-local PASS lines. Mapped upstream coverage remains `654 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Dependency closure: no new support component is needed. The slice reuses lane-local parser-level SELECT SQL, recursive CTE tracing, compound SELECT, window metric execution, spillover drains, replay tickets, and promotion-snapshot machinery.

Non-overlap: avoids accepted next238 source-generation seals, next240 spillover drains, next243 replay tickets alone, next245 next-source promotion snapshots alone, accepted batch214 next245 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters. The narrower behavior is the post-promotion recursive-lineage/window-metric epoch fence for current-source next249 admission.
