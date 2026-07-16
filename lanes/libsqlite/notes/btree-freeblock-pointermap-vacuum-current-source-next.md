# B-tree Freeblock Pointer-Map Vacuum Current Source Next

This slice adds `SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan`, a bounded current-source write-apply helper that keeps the deleted leaf page materialized with its reusable coalesced freeblocks while obsolete overflow tail pages are released through the freelist and then truncated by incremental vacuum across an auto-vacuum pointer-map page boundary.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextTest.php`
- Result: `1 test files, 289 assertions, 0 failures`, with 92 PASS lines.
- `php lanes/libsqlite/examples/application-btree-freeblock-pointermap-vacuum-current-source-next.php --self-test`
- Result: `application-btree-freeblock-pointermap-vacuum-current-source-next self-test passed`

Non-overlap:

- Avoids accepted batch89 overflow freeblock coalescing by adding the incremental-vacuum materialization step after coalescing.
- Avoids accepted overflow freelist release by proving pointer-map boundary truncation and surviving/truncated freelist state from the combined current-source page images.
- Avoids accepted page-move/root-collapse/index-interior merge paths; this slice does not relocate live b-tree pages.

Dependency closure:

- No new support component is needed. The implementation reuses existing bounded `SQLiteBTreeFreeblockCoalescePlan`, `SQLiteOverflowVacuumTruncatePlan`, auto-vacuum pointer-map readers, and native PHP page-image materialization.

Expected dashboard movement:

- `phpPass`: `35916 -> 35986` from the 70 newly passing focused PASS lines.
- `benchmarkDenominator.mapped`: `524 -> 525` for the newly mapped B-tree freeblock plus pointer-map vacuum materialization evidence row.
