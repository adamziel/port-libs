# B-tree Overflow Delete Pointer-map Current-source Next86

This slice adds `SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan`, a
bounded current/next delete helper that derives obsolete overflow page chains
from the current database image for each table/index leaf delete instead of
requiring callers to pass the next delete's overflow page list by hand.

Behavior covered:

- table leaf current/next transient deletes release overflow pages read from
  live overflow next pointers;
- index leaf current/next deletes preserve overflow-backed record matching via
  an overflow reader and release current-source overflow chains;
- auto-vacuum pointer-map entries transition from first-overflow/overflow-page
  ownership to free-page across current and next delete phases;
- table/index leaf defragmentation can compact a page after one
  overflow-backed cell is deleted while surviving overflow-backed cells remain
  on the page.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowDeletePointerMapCurrentSourceNext86Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-btree-overflow-delete-pointermap-current-source-next86.php
# released_overflow_pages: [6,8]
# pointer_map_transition_pages: [6,8]
# after_next_pointer_map_types: ["free-page","free-page"]
```

Non-overlap:

Avoids accepted pointer-map vacuum, page relocation, root collapse,
index-interior merge, bulk overflow freeblocks, overflow freelist release,
batch82 index overflow rebalance/freelist application, and current-next80
manual overflow-page materialization. The new behavior is current-source
overflow-chain discovery plus pointer-map transition evidence during
consecutive leaf deletes.

Dependency closure:

No new support component is needed. The slice composes existing native PHP
database image reads, overflow-page traversal, leaf delete/freeblock
compaction, freelist release, and auto-vacuum pointer-map helpers.
