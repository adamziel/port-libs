# B-tree Vacuum Pointer-map Freeblock Current-source Next184

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext184Plan`.
- Builds on next181 snapshot rows and creates a current-source cursor contract for a copied Application database after deleting a large transient row, partially vacuuming the obsolete overflow tail, and writing replacement overflow pages.
- Admits only materialized leaf/overflow pages with contiguous source ordinals, carries the secure-delete freeblock scrub receipt for the table leaf, verifies the terminal overflow page keeps its rewritten zero next-pointer receipt, and keeps the truncated tail excluded behind its pointer-map fence receipt.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext184Test.php`
  - `1 test files, 941 assertions, 0 failures`
  - `101` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next184.php`
  - self-test passed

## Non-overlap

This is additive after next181 reader snapshots. It does not repeat next181 snapshot admission, next178 publication receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks. The new surface is the current-source cursor materialization and scrub/terminal receipt gate used before replaying the next source image.

## Dependency Closure

No new support component is needed. The slice reuses native b-tree page images, secure-delete leaf freeblock receipts, overflow next-pointer receipts, and auto-vacuum pointer-map metadata already present in the libsqlite lane.
