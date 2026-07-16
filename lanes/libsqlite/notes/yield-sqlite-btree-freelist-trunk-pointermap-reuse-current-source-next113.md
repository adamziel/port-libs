# B-tree Freelist Trunk Pointer-Map Reuse Current Source Next113

## Scope

This B-tree slice covers the current-source to next-source boundary where a
freelist trunk page is itself reused as a new B-tree page after its leaf slots
are consumed:

- current freelist trunk page `4` points at next trunk page `106` and contains
  free leaf page `5`;
- allocating two B-tree pages consumes leaf `5`, then reuses trunk `4`;
- the next freelist head advances to trunk `106`, preserving its remaining
  leaf `107`;
- auto-vacuum pointer-map entries for reused pages `5` and `4` move from
  `free-page` parent `0` to `btree-page` parent `42`;
- the reused trunk page materializes as an index leaf, proving stale freelist
  trunk header bytes are overwritten before the page is visible as B-tree
  content.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNext113Test.php`
- `php -l lanes/libsqlite/examples/application-btree-freelist-trunk-pointermap-reuse-current-source-next113.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNext113Test.php`
  - `1 test files, 375 assertions, 0 failures`
  - 75 focused PASS lines
- `php lanes/libsqlite/examples/application-btree-freelist-trunk-pointermap-reuse-current-source-next113.php`
  - emits JSON with allocated pages `[5,4]`, next freelist head `106`, and
    trunk pointer-map reuse rows showing `free-page -> btree-page`.

## Non-Overlap

This avoids accepted overflow freelist release, next104 overflow release plus
incremental-vacuum survivor reuse, next107 overflow rebalance apply, page
relocation, root collapse, index-interior merge, bulk overflow freeblocks, and
PRAGMA pointer-map/freelist integrity diagnostics. The new surface is the
allocator boundary where the current freelist trunk page itself is reused while
another trunk remains as the next freelist head.

## Dependency Closure

No new support component is needed. The patch composes existing native PHP
SQLite database-image, freelist trunk allocation, B-tree page-image, and
auto-vacuum pointer-map primitives.

## Expected Status Delta

- `phpPass`: `43574 -> 43649` (+75 focused PASS lines)
- mapped coverage: unchanged at `604 / 1589`
- root harness: not run from this isolated micro-slice
