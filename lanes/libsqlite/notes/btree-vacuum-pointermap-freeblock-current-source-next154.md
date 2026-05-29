# B-tree vacuum pointer-map freeblock current-source next154

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a current-source wrapper over the existing table-leaf delete/freeblock and pointer-map vacuum path.
- The new surface records overflow next-page pointers from the source database image before release, compares them with the delete-result chain, reports post-vacuum materialization for surviving pages, and keeps the compacted leaf freeblock integrity summary alongside the pointer-map transition.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext154Test.php` -> `1 test files / 235 assertions / 0 failures` with 65 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next154.php --self-test`.

## Non-overlap

This avoids accepted page relocation, root collapse, index-interior merge, bulk overflow freeblocks, overflow freelist release, freelist trunk pointer-map reuse, next135 partial pointer-map/freeblock vacuum, next142/147 overflow freeblock pointer-map allocation, and batch147 freelist vacuum pointer-map behavior. The new behavior is the current-source overflow next-pointer audit immediately before freeblock/vacuum apply, including mismatch surfacing for stale delete-result chains.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP SQLite page images, table leaf deletion, overflow chain page reads, pointer-map entries, freelist/vacuum materialization, and B-tree freeblock integrity helpers.
