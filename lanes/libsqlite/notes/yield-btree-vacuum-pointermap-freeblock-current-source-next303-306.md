## B-tree Vacuum Pointer-map Freeblock Current Source Next303-306

Prepared an isolated follow-on slice for `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` after the ready next299-302 freelist splice work.

This slice adds `tableLeafFromDeleteResultNext303()` through `tableLeafFromDeleteResultNext306()` and keeps them on the existing pointer-map-scoped freelist splice path. The coverage verifies that reusable pages are sealed into trunk/leaf-slot receipts only after next261 vacuum finalization, with fenced tail overflow pages rejected from the freelist.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext303306Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next303-306.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext303306Test.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next303-306.php`
- `git diff --check`

Next slice: continue with btree vacuum pointer-map/freeblock current-source next307-310 without claiming unrelated pager, JSON, VFS, WAL, SQL, encoding, or team-state files.
