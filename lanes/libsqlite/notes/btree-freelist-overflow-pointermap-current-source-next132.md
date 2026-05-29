# btree-freelist-overflow-pointermap-current-source-next132

Status: focused B-tree behavior growth for current-source next132.

Behavior:
- Adds `SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan`.
- Reads two obsolete overflow chains from the current database image through their real next-page pointers before freeing them.
- Releases both chains into the freelist with auto-vacuum pointer-map entries changed to `free-page` parent `0`.
- Allocates a replacement overflow chain from the resulting freelist and rewrites pointer-map parents to match the newly materialized overflow order, including an existing freelist page before reused obsolete overflow pages.

WordPress smoke:
- `php lanes/libsqlite/examples/wordpress-btree-freelist-overflow-pointermap-current-source-next132.php --self-test`

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNext132Test.php`
- Result: `1 test files, 286 assertions, 0 failures` with 76 PASS lines.

Non-overlap:
- Avoids accepted overflow freelist release, bulk overflow freeblocks, overflow freeblock pointer-map reuse next128, pointer-map/freelist overflow next129, root collapse, page relocation, and freelist trunk pointer-map reuse. This slice records current-source next-pointer rows for two obsolete chains and then verifies replacement-chain pointer-map parents after mixed existing-freelist/released-overflow allocation.

Dependency closure:
- No new support component needed. The slice reuses native PHP SQLite database images, overflow-chain parsing, freelist release/allocation, pointer-map mutation, and overflow page encoding.
