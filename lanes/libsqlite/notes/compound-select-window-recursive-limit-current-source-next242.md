# compound-select-window-recursive-limit-current-source-next242

Status: focused PHP behavior growth for current-source compound SELECTs where recursive CTE `LIMIT/OFFSET`, per-arm window rows, and the final compound page must all be acknowledged before a next-source cursor is admitted.

Behavior covered:

- `WITH RECURSIVE` queue LIMIT/OFFSET rows feed one compound arm.
- `dense_rank()` window output is evaluated inside the recursive and `wp_options` arms before `UNION`/`EXCEPT`.
- The final compound `ORDER BY ... LIMIT ... OFFSET` page changes when a next-source `wp_options` row is introduced.
- A next242 commit fence records recursive queue, window output, and final page tokens and rejects stale or incomplete cursor acknowledgements.

WordPress path: `wordpress-compound-select-window-recursive-limit-current-source-next242.php` models a copied `wp_options` import where a plugin option crosses the displayed compound page while recursive dependency rows keep their window rank.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext242Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext242Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next242.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext242Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next242.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused `phpPass +85` from the new test file. `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed; this reuses native PHP `SQLiteSelectSql` compound execution, recursive CTE queue LIMIT/OFFSET, window row-array output, and the accepted next238 source-generation seal.

Non-overlap: avoids accepted batch209 next238 compound/window/recursive LIMIT behavior, next235 promotion barriers, next238 source-generation seals, SELECT SQL grouped/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, row-value/window RETURNING, trigger recursive UPSERT, planner/PRAGMA/ATTACH, encoding, and suite evidence handoffs. The narrower surface is a commit fence over recursive queue, window output, and final compound page acknowledgements.
