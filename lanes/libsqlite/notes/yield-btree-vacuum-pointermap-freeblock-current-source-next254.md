# B-tree Vacuum Pointer-Map Freeblock Current-Source Next254

## Behavior

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan`.
- The plan consumes next249 next-source allocation rows and publishes page-local current-source freeblock write slots only after pointer-map anchors are active.
- It verifies next-source token carry-forward, pointer-map ordering, page-local freeblock offsets, reusable receipts, monotonic allocation positions, and current-source token chaining.

## Application Smoke

- Added `examples/application-btree-vacuum-pointermap-freeblock-current-source-next254.php`.
- The smoke models deletion of an overflow-backed copied `wp_options` transient and verifies pointer-map anchor pages `[2, 105]`, reusable write pages `[3, 106, 107, 108]`, and page-local write offsets `[0, 40, 0, 128, 0, 144, 160]`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next254.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Test.php`
  - `1 test files, 1495 assertions, 0 failures`
  - 151 PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next254.php`
  - emitted `application-btree-vacuum-pointermap-freeblock-current-source-next254 self-test passed`

## Non-Overlap

This slice adds current-source freeblock write-slot publication after next249 next-source allocation rows. It avoids accepted next249 allocation ordering, next245 cursor admission, next242 visibility, next238 freelist admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL planner, and encoding clusters.

## Dependency Closure

No new support component is needed. The slice reuses native SQLite database/page parsing, pointer-map entries, table leaf delete helpers, and existing B-tree current-source/freeblock plans.
