# B-tree Vacuum Pointer-map Freeblock Current-source Next215

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan`.
- Builds on next212 current-source page apply rows and records commit receipts for only the pages that were applied from the current source.
- Verifies pointer-map pages commit before payload/freeblock pages, the deleted leaf freeblock receipt remains committed, and truncated tail overflow pages 109-110 stay fenced from the next writer commit set.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next215.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Test.php`
  - `1 test files, 991 assertions, 0 failures`
  - `141` PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next215.php`
  - `wordpress-btree-vacuum-pointermap-freeblock-current-source-next215 self-test passed`

## Non-overlap

This is the commit-receipt step after next212 page apply. It does not repeat next212 apply ordering, next209 source latching, next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or the accepted current-source next207 behavior.

## Dependency Closure

No new support component is needed. The slice reuses native B-tree page images, overflow delete/vacuum metadata, pointer-map pages, leaf freeblock receipts, and current-source apply rows already present in `lanes/libsqlite/src`.
