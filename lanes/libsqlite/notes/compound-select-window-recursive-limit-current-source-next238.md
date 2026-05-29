# Compound SELECT Window Recursive LIMIT Current Source Next238

Status: focused PHP behavior growth for current-source compound SELECTs where
recursive CTE LIMIT rows and per-arm window output must both be acknowledged
before a changed next-source final LIMIT page can be admitted.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`,
layered on the accepted next235 promotion barrier. The new surface is a
source-generation seal over the final compound LIMIT boundary: the current and
next admitted labels, skipped offset rows, truncated rows, and page hashes must
match explicit acknowledgements before a next-source cursor is published.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next238.php`
models a copied `wp_options` import preview where a new autoloaded plugin row
moves across the compound `UNION`/`EXCEPT` final `LIMIT/OFFSET` page while
recursive dependency rows keep their window rank.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext238Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next238.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext238Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next238.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +76` from the new focused test file.
Mapped coverage remains unchanged because this is current-source PHP behavior
over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next226 sum/count EXCEPT+INTERSECT behavior,
next229 dense-rank source tokens, next232 page acknowledgements, next235
promotion barriers, batch207 next235 accepted compound/window/recursive LIMIT
coverage, row-value/window RETURNING, trigger recursive UPSERT, JSON table,
WAL/VFS, B-tree, planner, PRAGMA, encoding, and suite evidence handoffs. The
narrower behavior is source-generation and final-boundary acknowledgement for
next-source admission after the accepted recursive/window promotion barrier.

Dependency closure: no new support component is needed; this reuses lane-local
SELECT SQL, recursive CTE tracing, compound SELECT, window rank execution, and
current-source cursor metadata.
