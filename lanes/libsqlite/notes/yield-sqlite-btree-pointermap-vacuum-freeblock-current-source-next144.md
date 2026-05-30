# SQLite B-tree Pointer-Map Vacuum Freeblock Current Source Next144

## Slice

- Added `SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan`.
- Behavior: audits a copied Application `wp_options` delete/vacuum flow by pairing the materialized deleted leaf page with the surviving overflow freelist trunk and the truncated overflow tail across an auto-vacuum pointer-map page.
- Non-overlap: avoids accepted page relocation/root-collapse, overflow freelist release, bulk overflow freeblock materialization, and prior next135/next139 rows by adding current-source materialized/truncated row hashes and pointer-map page/type/parent evidence around the final vacuum image.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Test.php`
- Result: `1 test files, 255 assertions, 0 failures`
- PASS-line delta: `+63`

## Application Smoke

- `php lanes/libsqlite/examples/application-btree-pointermap-vacuum-freeblock-current-source-next144.php --self-test`
- Scenario: copied `wp_options` transient delete preserves page `106` as the surviving freelist trunk, truncates pages `107..110`, and records current/next pointer-map ownership for the leaf and released overflow pages.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP SQLite page/header, pointer-map, freelist truncation, and table-leaf delete helpers already present under `lanes/libsqlite/src`.

## Next

- Continue B-tree work on non-overlapping delete/rebalance/freelist materialization or pointer-map apply paths that are not covered by accepted page-move/root-collapse/overflow-release clusters.
