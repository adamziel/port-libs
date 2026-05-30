# B-tree Overflow Freeblock Current Next72

This slice adds `SQLiteBTreeOverflowFreeblockCurrentNextPlan`, a bounded native
plan for the current/next boundary after an overflow-backed delete releases
obsolete overflow pages and the next replacement payload immediately needs a
new overflow chain. The plan releases the old overflow pages into the current
freelist, allocates replacement overflow pages from that released state, writes
the replacement overflow page images, and rewrites auto-vacuum pointer-map
ownership from `first-overflow-page` through the subsequent `overflow-page`
links.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockCurrentNext72Test.php
# 1 test files, 57 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-overflow-freeblock-current-next72.php
```

The smoke reports a copied `wp_options` transient delete where obsolete
overflow pages `5,6` are released, the replacement overflow chain reuses
current/next freelist pages `10,6,5,4`, and pointer-map ownership becomes
`first-overflow-page` for the owning b-tree page followed by linked
`overflow-page` entries.

Non-overlap:

This avoids accepted bulk overflow freeblock materialization, overflow freelist
release, pointer-map vacuum append allocation, empty-leaf batch free, B-tree
page move/root-collapse/index-interior merge, and standalone freeblock
coalescing. The new behavior is the composed delete-release plus next
replacement overflow-chain write from the current freelist state.

Dependency closure:

No new support component is needed. The slice reuses lane-local overflow-page
encoding, freelist release/allocation, auto-vacuum pointer-map, and database
page-image primitives.
