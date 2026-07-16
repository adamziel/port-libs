# B-tree Vacuum Pointer-Map Freeblock Current Source Next191

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan`, a current-source handoff manifest layered on the accepted next188 reader-admission behavior. The manifest publishes only pages that are readable under the final page-count fence, keeps pointer-map pages ordered before replacement overflow pages, preserves the secure-delete freeblock receipt for the table leaf page, and excludes truncated tail pages from the next source.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Test.php`
  - `1 test files, 752 assertions, 0 failures`
  - 92 PASS lines

## Application Smoke

- `lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next191.php`
  demonstrates a copied `wp_options`-style leaf page with a deleted transient row, replacement overflow pages, pointer-map page publication, and tail-page fencing after vacuum.

## Non-Overlap

This slice does not repeat next188 reader admission, next185 durability receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, accepted pointer-map page-move clusters, or accepted B-tree merge/rebalance summaries.

## Dependency Closure

No new support component is needed. The slice reuses native SQLite page images, table leaf delete/freeblock helpers, pointer-map entries, overflow page materialization, and current-source reader admission already present in the libsqlite lane.
