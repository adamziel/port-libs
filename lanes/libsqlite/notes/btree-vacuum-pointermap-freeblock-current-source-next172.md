# B-tree Vacuum Pointer-map Freeblock Current-source Next172

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext172Plan`.
- Builds on accepted next166 write admission and materializes the complete post-vacuum database image.
- Verifies admitted page images rewrite the header, deleted leaf freeblock page, pointer-map page, and replacement overflow pages.
- Verifies rejected current-source pages are truncated from the final image instead of being written back.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext172Test.php`
  - `1 test files, 751 assertions, 0 failures`
  - `103` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next172.php`
  - self-test passed

## Non-overlap

This is additive after next166 write admission. It does not repeat next166 write admission, next163 source fencing, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks.

## Dependency Closure

No new support component is needed. The slice reuses native database page images, b-tree freeblock pages, overflow allocation, and pointer-map write admission already present in the libsqlite lane.
