# Compound SELECT Window Recursive LIMIT Current Source Next244

Status: focused PHP behavior growth for current-source compound SELECTs where
the yielded next-source cursor must wait for a recursive LIMIT exhaustion fence.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`,
layered on accepted next241 final-row resume admission. The new surface is a
recursive/window acknowledgement fence over current and next recursive queue
trace tokens, per-arm window tokens, and final compound LIMIT rows. A cursor
may reuse the yielded next-source position only after all recursive LIMIT fence
acknowledgements match.

Application path:
`application-compound-select-window-recursive-limit-current-source-next244.php`
models a copied `wp_options` import preview where a new autoloaded plugin row
crosses the compound `UNION`/`EXCEPT` final page while recursive dependency
rows keep their `dense_rank()` output.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext244Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next244.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext244Test.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next244.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +76` from the new focused test file.
Mapped coverage remains unchanged because this is current-source PHP behavior
over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next226/228/230/232/235/238/241 compound handoffs,
suite next244 veryquick evidence, row-value/window RETURNING, trigger recursive
UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, and VDBE
clusters. The narrower behavior is recursive LIMIT exhaustion admission after
the accepted final-row resume receipt.

Dependency closure: no new support component is needed; this reuses lane-local
SELECT SQL, recursive CTE tracing, compound SELECT, window rank execution, and
current-source cursor metadata.
