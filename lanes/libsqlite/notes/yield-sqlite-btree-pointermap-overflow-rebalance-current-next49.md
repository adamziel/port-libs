# B-tree Pointer-Map Overflow Rebalance Current/Next49

Implemented focused pointer-map visibility for overflow pages released by a
table-leaf delete/rebalance plan:

- `SQLiteFreelistFreePlan` now carries `freedPointerMapEntries`, the post-free
  pointer-map entries for pages moved to the freelist.
- `SQLiteBTreeFreeblockFreelistRebalancePlan::toArray()` exposes those entries
  so current/next pointer-map pages can be audited without reparsing page bytes.
- Focused coverage proves four obsolete overflow pages released from an
  oversized copied `wp_options` transient become `free-page` pointer-map entries
  with parent `0` across pointer-map pages `2` and `105`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapOverflowRebalanceCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 95 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-btree-pointermap-overflow-rebalance-current-next49.php
freedOverflowPages: [8, 104, 107, 205]
updatedPointerMapPages: [2, 105]
freedPointerMapEntries: all free-page, parent_page_number 0
```

Non-overlap:

- Avoids accepted table/index page relocation, root collapse, overflow freelist
  release, bulk overflow freeblocks, and freeblock-only rebalance diagnostics.
- This slice records the current/next pointer-map entries produced by overflow
  release during rebalance so integrators can verify auto-vacuum ownership
  clearing as part of the page-image plan.

Dependency closure:

- No new support component is needed; this reuses the native PHP b-tree,
  overflow-page, freelist, and pointer-map primitives already in the lane.
