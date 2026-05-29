# B-tree Pointer-Map Vacuum Freeblock Current Source Next127

## Behavior

This slice adds `SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan`, which composes a current-source table/index leaf delete-freeblock apply with freelist tail truncation. It preserves the materialized defragmented leaf page image, releases obsolete overflow pages to free-page pointer-map state, and classifies which released pages survive as freelist pages versus which tail pages are omitted by incremental-vacuum truncation.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext127Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-pointermap-vacuum-freeblock-current-source-next127.php --self-test`
- Expected focused movement: 73 new PASS lines in one lane-scoped test file.

## Non-Overlap

This does not repeat accepted overflow freelist release, pointer-map reuse, page relocation, root collapse, or next122 standalone coalesced overflow-vacuum coverage. The new behavior verifies current-source delete-freeblock materialization and auto-vacuum tail truncation in one applied plan for table and index leaves.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP b-tree page, record, freelist, pointer-map, and auto-vacuum truncation primitives.
