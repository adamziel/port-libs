# B-tree Overflow Pointer-map Freepage Pointer Map Freepage

This slice extends `SQLiteBTreeOverflowVacuumFreepagePlan` with a current-source/next summary for obsolete overflow pages that are released into the freelist under auto-vacuum.

Behavior covered:

- Preserves the source label and chain order for table and index overflow deletes.
- Reports the original pointer-map ownership (`first-overflow-page` / `overflow-page`) and parent page for each obsolete overflow page.
- Reports the next pointer-map state after free-list release as `free-page` with parent `0`.
- Distinguishes the newly promoted freelist trunk from leaf pages, exposes traversal position, and exposes next allocation order.
- Keeps secure-delete materialization visible, including the trunk-page exception where freelist header bytes are retained.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowPointerMapFreepageTest.php`
- `php lanes/libsqlite/examples/application-btree-overflow-pointermap-freepage.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowVacuumFreepagePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowPointerMapFreepageTest.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-pointermap-freepage.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

Avoids accepted page relocation, root collapse, index-interior merge, bulk overflow freeblocks, overflow freelist release, overflow freeblock truncate, pointer-map vacuum append/rebalance, and B-tree overflow delete pointer-map current-source slices. This adds the missing current-source to next free-page pointer-map/freelist role surface on the existing overflow vacuum freepage plan rather than another delete/rebalance or truncation materializer.

Dependency closure:

No new support component is needed. The patch composes existing native SQLite database image, overflow-chain, freelist, secure-delete, and pointer-map helpers.
