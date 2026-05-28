# compound-select-window-recursive-limit-current-source-next245

- Behavior: adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext245Plan`, a bounded next-source promotion snapshot for compound SELECT plans that combine recursive CTE LIMIT/OFFSET, window ranking, INTERSECT/EXCEPT membership, and final LIMIT/OFFSET paging.
- WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next245.php` models `wp_options` current-source rows where a next-source `plugin_prime` option displaces `rewrite_rules` after the current replay/spillover fences have already been acknowledged.
- Focused tests: `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext245Test.php` adds 85 PASS cases, including 72 generated variants.
- Non-overlap: extends accepted next243 replay-ticket behavior with a next-source delta snapshot; avoids next242/next243 accepted commit/replay-only fences, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, and suite evidence clusters.
- Dependency closure: no new support component needed; this reuses native SELECT SQL compound execution, recursive LIMIT/OFFSET tracing, window output, next240 spillover fencing, and next243 replay tickets.
