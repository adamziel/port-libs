## B-tree Pointer-map Vacuum Rebalance Current/Next 73

This slice extends `SQLiteBTreeEmptyLeafBatchFreePlan` summaries so empty leaf
delete/rebalance planning exposes the underlying freelist transition when the
current freelist trunk is full. In that boundary, SQLite promotes the first
deleted empty leaf into the next freelist trunk, appends the next deleted leaf
as a freelist leaf, rewrites both auto-vacuum pointer-map entries to
`free-page`, and secure-delete clears only the appended freelist leaf because
the promoted page now stores trunk metadata.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumRebalanceCurrentNext73Test.php
# 1 test files, 55 assertions, 0 failures

php lanes/libsqlite/examples/application-btree-pointermap-vacuum-rebalance-current-next73.php
# Application transient delete rebalance promoted trunk page: 3
# Application transient delete rebalance appended leaf page: 5
# Application transient delete pointer-map pages: 2
# Application transient delete secure-cleared pages: 5
```

Non-overlap: this avoids accepted page relocation, root collapse, overflow
freelist release, pointer-map vacuum append allocation, pointer-map truncation,
bulk overflow freeblocks, and batch66 pointer-map apply. The new surface is
the current/next rebalance boundary where batched empty leaf release must expose
freelist trunk promotion and pointer-map free-page application.

Dependency closure: no new support component is needed; this reuses native PHP
SQLite header, freelist trunk, B-tree leaf delete, and auto-vacuum pointer-map
helpers already present in the lane.
