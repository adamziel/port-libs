# Compound window recursive affinity current-source next147

Behavior slice: adds `SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan`, a current-source cursor fence over the existing recursive compound/window/affinity rowset. It pages current and next rowsets only after recursive `UNION` numeric-affinity deduplication and per-arm window evaluation, then rejects stale resume cursors whose offset or current/next source signatures no longer match.

WordPress smoke: `wordpress-compound-window-recursive-affinity-current-source-next147.php` models copied `wp_options` dependency-walk import diagnostics where a repair UI pages through current and next recursive compound rows without losing left-most output names or admitting stale cursor state after plugin rows are added.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveAffinityCurrentSourceNext147Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-window-recursive-affinity-current-source-next147.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveAffinityCurrentSourceNext147Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-window-recursive-affinity-current-source-next147.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next142 recursive affinity window rowset behavior, next143 EXCEPT final ORDER behavior, compound SELECT row composition, grouped SELECT text, SQL expression ORDER BY, JSON table source/cursor/constraint work, and VFS/WAL/B-tree current-source clusters. The new boundary is stale-cursor checked paging across current and next compound rowsets after the existing recursive/window/affinity materialization.

Dependency closure: no new support component is needed; next147 reuses native recursive CTE, compound SELECT, window, affinity, and current-source rowset helpers and adds only lane-local cursor fencing.
