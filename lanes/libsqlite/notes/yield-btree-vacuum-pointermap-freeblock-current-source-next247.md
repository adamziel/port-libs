# B-tree Vacuum Pointer-Map Freeblock Current Source Next247

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan`.
- Composes accepted next244 publish cursor rows and adds current-source checkpoint admission for pointer-map/freeblock visibility.
- Proves checkpoint pages match publish pages, pointer-map pages are admitted before payload pages, duplicate pointer-map generations are retained, freeblock receipts are checkpointed, and vacuum-truncated tail pages stay fenced.
- Adds focused test `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Test.php`.
- Adds WordPress smoke `wordpress-btree-vacuum-pointermap-freeblock-current-source-next247.php`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Test.php` => `1 test files, 1411 assertions, 0 failures` with 131 PASS lines.
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next247.php` => expected JSON summary plus `wordpress-btree-vacuum-pointermap-freeblock-current-source-next247 self-test passed`.

Non-overlap:

This slice adds current-source checkpoint admission after next244 publish visibility. It does not repeat next244 publish cursor construction, next241 source cursor rows, next238 freelist-link admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization.

Dependency closure:

No new support component is needed. The slice reuses existing native B-tree, pointer-map, overflow, table-leaf, and current-source publish cursor helpers.
