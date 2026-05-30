# B-tree Vacuum Pointer-map Freeblock Current Source Next155

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, covering the next-use boundary after a delete releases overflow pages and incremental vacuum truncates the tail. The surviving released overflow page remains a freelist trunk, is allocated as a new B-tree page, and its auto-vacuum pointer-map entry transitions from `first-overflow-page` to `free-page` to `btree-page`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next155.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Test.php`
  - `1 test files, 182 assertions, 0 failures`
  - `62` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next155.php`
  - emits `allocatedBtreePages` `[106]`, `reusedVacuumFreePages` `[106]`, pointer-map transition `["first-overflow-page","free-page","btree-page"]`, and no remaining freelist pages.

Non-overlap: avoids accepted overflow freelist release, bulk overflow freeblock materialization, pointer-map vacuum freeblock next135/next144 summaries, pointer-map freeblock rebalance next146, overflow vacuum allocation/reuse next104/119/132/142, root collapse, table/index page relocation, and freelist trunk pointer-map reuse. This slice is specifically the post-vacuum allocation of the surviving free page as the next B-tree page image.

Dependency closure: no new support component is needed. The slice reuses native SQLite database images, table leaf cell codecs, delete/rebalance freeblock application, incremental vacuum truncation, freelist allocation, and auto-vacuum pointer-map mutation.
