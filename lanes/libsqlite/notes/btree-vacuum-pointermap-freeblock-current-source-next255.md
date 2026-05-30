# btree vacuum pointermap freeblock current-source next255

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan`.
- Focused behavior: publishes the current-source next-page cursor after next251 admission, preserving pointer-map visibility before payload/freeblock publication, carrying duplicate pointer-map generation rows, and keeping truncated tail pages fenced.
- Application smoke: `examples/application-btree-vacuum-pointermap-freeblock-current-source-next255.php` models copied `wp_options` transient deletion where overflow pages cannot become reusable current-source pages until pointer-map/freeblock publication is complete.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next255.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Test.php`
- Result: `1 test files, 1330 assertions, 0 failures` with 130 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next255.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This slice adds current-source next-page publication after next251 cursor admission. It does not repeat next251 admission, next248 sealing, next250/next251 accepted behavior, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or freelist trunk pointer-map reuse.

Dependency closure:

No new support component is needed. The patch reuses existing native B-tree page, pointer-map, table-leaf delete, overflow, and current-source admission helpers.
