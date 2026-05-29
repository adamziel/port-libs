# B-tree Vacuum Pointer-Map Freeblock Current Source Next156

## Scope

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` for a copied `wp_options` delete/rewrite path that:

- materializes the deleted table leaf page and released overflow chain through the existing pointer-map vacuum/freeblock plan,
- allocates replacement overflow bytes only from pages that survived vacuum as materialized freelist pages,
- rejects truncated overflow and pointer-map pages as allocation sources, and
- reports the final pointer-map ownership for the reused replacement overflow page.

This deliberately avoids the accepted table/index page relocation, root collapse, bulk overflow freeblock materialization, overflow freelist release, and next144/148/149 boundary-only surfaces by adding the post-vacuum replacement allocation check for the freeblock current-source path.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Test.php`
  - `1 test files, 368 assertions, 0 failures`
  - `74` PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next156.php --self-test`
  - `wordpress-btree-vacuum-pointermap-freeblock-current-source-next156 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next156.php`
- `git diff --check -- lanes/libsqlite`
  - no output

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP B-tree page header, table leaf page, overflow page, freelist allocation, SQLite database image, and auto-vacuum pointer-map primitives.
