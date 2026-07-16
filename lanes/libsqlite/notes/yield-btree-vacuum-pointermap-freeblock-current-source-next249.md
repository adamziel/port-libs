# B-tree Vacuum Pointer-Map Freeblock Current-Source Next249

## Behavior

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan`.
- The plan consumes next245 admitted cursor rows and publishes next-source allocation rows only after pointer-map epochs are open.
- It carries forward cursor tokens, leaf freeblock receipts, stable trunk candidate state, fenced tail-page status, and monotonic next-allocation positions.

## Application Smoke

- Added `examples/application-btree-vacuum-pointermap-freeblock-current-source-next249.php`.
- The smoke models deletion of an overflow-backed copied `wp_options` transient and verifies pointer-map epoch pages `[2, 105]`, reusable allocation pages `[3, 106, 107, 108]`, and allocation positions `[0, 1, 1, 2, 2, 3, 4]`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Test.php`
  - `1 test files, 1591 assertions, 0 failures`
  - 151 PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next249.php`
  - emitted `application-btree-vacuum-pointermap-freeblock-current-source-next249 self-test passed`

## Non-Overlap

This slice adds next-source allocation publication after next245 cursor admission. It avoids accepted next245 cursor ordering, next242 current-source visibility, next238 freelist admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL planner, and encoding clusters.

## Dependency Closure

No new support component is needed. The slice reuses native SQLite database/page parsing, pointer-map entries, table leaf delete helpers, and existing B-tree current-source/freeblock plans.
