# B-tree Vacuum Pointer-Map Freeblock Current Source Next195

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext195Plan`, a current-source replay cursor layered on the next191 handoff manifest. The replay rows assign contiguous 512-byte stream ranges only to pages published inside the final page-count fence, keep pointer-map replay before replacement overflow replay, preserve the secure-delete table leaf freeblock receipt, and omit truncated tail pages without leaking hash/resume material.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext195Test.php`
  - `1 test files, 950 assertions, 0 failures`
  - 100 PASS lines

## Application Smoke

- `lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next195.php`
  demonstrates a copied `wp_options`-style leaf page where deleting an obsolete transient creates a secure-delete freeblock, replacement overflow pages are replayed from the current source after pointer-map replay, and truncated overflow tail pages remain omitted from the replay cursor.

## Non-Overlap

This slice adds replay cursor tickets after the accepted next191 handoff manifest. It does not repeat next191 manifest construction, next188 reader admission, next185 durability receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted pointer-map page-move clusters.

## Dependency Closure

No new support component is needed. The slice reuses native SQLite page images, table leaf delete/freeblock helpers, pointer-map entries, overflow page materialization, and current-source handoff rows already present in the libsqlite lane.
