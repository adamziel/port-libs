# B-tree Delete Overflow Materialization Current Next75

This slice adds `SQLiteBTreeDeleteOverflowMaterializationPlan`, a bounded
current/next materializer for B-tree delete/rebalance page-image plans. It
turns existing empty-leaf, freeblock/rebalance, and overflow current-next page
images into a next `SQLiteDatabase` image and exposes page SHA1 transitions plus
auto-vacuum pointer-map transitions.

Focused evidence:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeDeleteOverflowMaterializationPlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationCurrentNext75Test.php
php -l lanes/libsqlite/examples/application-btree-delete-overflow-materialization-current-next75.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationCurrentNext75Test.php
```

Result: `1 test files, 58 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-delete-overflow-materialization-current-next75.php
```

Output:

```text
Application btree75 materialized action: btree-empty-leaf-batch-free
Application btree75 next freelist trunk: 3
Application btree75 next freelist count: 190
Application btree75 pointer-map transitions: 3,5,6,9,10
Application btree75 updated pages: 1,2,3,5,6,9,10
```

Non-overlap: this does not repeat accepted overflow freelist release, bulk
overflow freeblocks, pointer-map vacuum/rebalance summaries, page relocation,
root collapse, index-interior merge, or VFS/WAL writer paths. The new behavior
is materializing the next database image after delete/rebalance plans release
empty table/index leaves and obsolete overflow pages, with current-vs-next page
digests and pointer-map transitions.

Dependency closure: no new support component is needed. This reuses existing
native PHP SQLite database image, freelist, pointer-map, empty-leaf, freeblock,
and overflow current-next plan primitives.
