# B-tree Vacuum Pointer-map Freeblock Current-source Next216

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a commit-receipt layer over the accepted next212 current-source apply rows. It records ordered page-write receipts for pointer-map and payload/freeblock pages, hashes each receipted page batch, preserves the pointer-map-before-payload barrier, carries leaf freeblock receipts, and keeps vacuum-truncated tail pages out of the receipted writer set.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next216.php` models deleting an overflow-backed copied `wp_options` transient, partially vacuuming the tail, then publishing only readable pointer-map, table-leaf freeblock, and replacement overflow pages to the next writer.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext216Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next216.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext216Test.php`
  - `1 test files, 1027 assertions, 0 failures`
  - `147` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next216.php`
  - emitted `wordpress-btree-vacuum-pointermap-freeblock-current-source-next216 self-test passed`

Non-overlap:

- Adds commit receipts after next212 apply ordering.
- Does not repeat next212 apply ordering, next209 writer-source latching, next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted freelist/pointer-map reuse slices.

Dependency closure:

- No new support component is needed; this reuses native B-tree leaf/freeblock, overflow allocation, incremental-vacuum truncation, pointer-map, and next212 current-source apply helpers.
