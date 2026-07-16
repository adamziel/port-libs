# B-tree vacuum pointer-map freeblock current-source next236

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan`, a current-source next-page cursor layer after accepted next233 checkpoint admission.

The slice models a copied `wp_options` transient delete with obsolete overflow pages, incremental vacuum fencing, and a secure-delete leaf freeblock receipt. It proves that the next current-source page cursor:

- follows the checkpoint page sequence `[2, 3, 105, 106, 105, 107, 108]`;
- exposes pointer-map generations before payload/freeblock pages advance;
- preserves duplicate pointer-map page `105` generations instead of collapsing rewrites;
- carries the current leaf freeblock receipt across every source-next row;
- keeps fenced tail pages `109` and `110` unavailable to the next source cursor.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next236.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Test.php`
  - `1 test files, 1332 assertions, 0 failures`
  - `132` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next236.php`
  - `application-btree-vacuum-pointermap-freeblock-current-source-next236 self-test passed`

## Non-overlap

This adds source-next cursor visibility after next233 checkpoint admission. It does not repeat next233 checkpoint construction, next229 resume windows, next224 cursor sequencing, next218 write receipts, overflow freelist release, page relocation, root collapse, index-interior merge, bulk overflow freeblock materialization, freelist trunk pointer-map reuse, or accepted batch204 next233 behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree page images, table-leaf delete/freeblock receipts, overflow-chain/vacuum fencing, pointer-map metadata, and next233 checkpoint rows.
