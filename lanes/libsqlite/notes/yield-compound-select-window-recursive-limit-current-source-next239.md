# compound-select-window-recursive-limit-current-source-next239

Behavior slice: current-source compound SELECT handoff now records a final LIMIT/OFFSET resume fence for recursive/windowed `UNION` / `EXCEPT` plans. The next-source cursor is held until the current LIMIT output signature, recursive queue signature, and window signature are acknowledged together, preventing a copied `wp_options` preview page from resuming against a shifted current/next source boundary.

Files:

- `src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext239Test.php`
- `examples/wordpress-compound-select-window-recursive-limit-current-source-next239.php`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext239Test.php` - `1 test files, 440 assertions, 0 failures`, 71 PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next239.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext239Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next239.php`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this composes existing compound SELECT execution, recursive LIMIT/OFFSET trace, dense-rank window dispatch, next235 current-source promotion barriers, and final LIMIT/OFFSET result fencing.

Non-overlap: extends accepted next235 promotion barriers with a final current LIMIT output resume signature. It avoids accepted compound row composition, next232 page acknowledgements, next235 page/recursive/window promotion token validation, JSON table, WAL/VFS, B-tree, encoding/collation, planner range-cost, trigger, PRAGMA, and suite-countability surfaces.
