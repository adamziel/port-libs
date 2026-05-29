# B-tree Freelist Pointer-map Reuse Current-source Next124

- Implemented `SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan`.
- Behavior: when `planPageFreeList()` receives a page while the current freelist trunk is full, the freed page is promoted to a new freelist trunk. The next B-tree allocation can then consume that promoted trunk page, materialize it as a B-tree page, and rewrite its auto-vacuum pointer-map entry from `free-page` to `btree-page` or `root-page`.
- WordPress smoke: `examples/wordpress-btree-freelist-pointermap-reuse-current-source-next124.php` models copied `wp_options` cleanup freeing a page while the freelist trunk is full, followed by immediate reuse for a replacement table leaf.
- New focused evidence: `SQLiteBTreeFreelistPointerMapReuseCurrentSourceNext124Test.php` adds 80 PASS lines / 296 assertions.
- Non-overlap: avoids accepted freelist trunk pointer-map reuse next113, overflow freelist/vacuum reuse next121, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, and delete/rebalance freeblock clusters. This slice covers the free-time promotion of a current B-tree page into a new freelist trunk and its immediate pointer-map-safe reuse.
- Dependency closure: no new support component is needed; this reuses existing native PHP SQLite page, freelist, pointer-map, and integrity-check primitives.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreelistPointerMapReuseCurrentSourceNext124Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-freelist-pointermap-reuse-current-source-next124.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistPointerMapReuseCurrentSourceNext124Test.php`
  - `1 test files, 296 assertions, 0 failures`
  - 80 PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-freelist-pointermap-reuse-current-source-next124.php`
