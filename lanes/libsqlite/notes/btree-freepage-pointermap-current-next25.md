# B-tree Freepage Pointer-map Current Next25

This slice adds focused coverage for auto-vacuum pointer-map state after
allocating B-tree pages from both the current freelist trunk and its next trunk.
The new plan evidence exposes the concrete pointer-map entries for newly
allocated pages, including root-page allocation, current/next trunk allocation,
and an append case that skips an auto-vacuum pointer-map page.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreepagePointerMapCurrentNext25Test.php` => `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-freepage-pointermap-current-next25.php` => allocated pages `[3,104,5,107,206,106]`, updated pointer-map pages `[2,105]`, and allocated pointer-map entries rewritten to `btree-page` parent `11`

Non-overlap: avoids accepted B-tree overflow freelist release, bulk overflow
freeblocks, table/index page moves, root collapse, index-interior merge, and
B-tree index delete rebalance. This is the allocation-side pointer-map evidence
for pages drained from current and next freelist trunks.

Dependency closure: no new support component is needed; the slice reuses native
PHP SQLite header, freelist trunk, pointer-map, and B-tree allocation
primitives already in `lanes/libsqlite/src`.
